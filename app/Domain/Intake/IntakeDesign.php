<?php

namespace App\Domain\Intake;

use App\Domain\Farm\FarmGate;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\FarmState;
use App\Domain\Farm\InsufficientCredit;
use App\Domain\Farm\Wallet;
use App\Domain\Generation\GenerationService;
use App\Domain\Generation\QuotaExceeded;
use App\Engines\Vision\VisionDescriber;
use App\Models\AnonymousSession;
use App\Models\FarmSetting;
use App\Models\GenerationRequest;
use App\Models\Intake;
use App\Models\User;
use App\Support\Money;
use App\Support\Track;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A free design from the home page (docs/AN.md §24.4): a wish no tool has a template for (or one the assistant was not
 * sure about) becomes a first 3D design made by the model generator from the words, or from the photo when it shows
 * the thing itself. The visitor then sees it in the calculator, where the size, the colour, the price, the order, the
 * download and "change it in words" already are.
 *
 * Generating costs money, so it is behind a switch that is OFF until the admin turns it on (/admin/intake/design),
 * behind the home page's own rules against advertising (a held wish never gets here) and behind counts (Roman,
 * 13 Nov 2026): `free_guest` runs for a visitor without an account, `free_user` more once they sign in, after that a
 * run is paid from the credit at `price` crowns, and while no price is set there is no further run at all, said
 * honestly. `per_day` caps the whole site and day. One use = one started run of the generator (a first design or a
 * "change it in words"); a copy of an earlier identical result, a failed run and a paid run are not counted.
 * The settings live in the farm's settings table under `home.design` (no migration); the defaults are in
 * config/home.php `intake.design`.
 */
final class IntakeDesign
{
    public const SETTING = 'home.design';

    /** Why a design cannot be started (`reason`); the page has a sentence for each (`router.design.<reason>`). */
    public const OFF = 'off';

    public const CLOSED = 'closed';

    public const WORDS = 'words';

    public const PERSON = 'person';

    public const DAY_LIMIT = 'day_limit';

    /** A visitor without an account used their free runs: signing in gives more. */
    public const GUEST_LIMIT = 'guest_limit';

    /** An account used its free runs and no price is set: there is no further run. */
    public const USER_LIMIT = 'user_limit';

    /** An account used its free runs and a price is set: the next run is paid, and only by the visitor's own press. */
    public const PAY = 'pay';

    /** … and the credit does not cover it. */
    public const CREDIT = 'credit';

    /** The reasons the page tells the visitor about without being asked (the switch being off is no news to them). */
    public const TOLD = [self::DAY_LIMIT, self::GUEST_LIMIT, self::USER_LIMIT, self::PAY, self::CREDIT];

    /** An address without the browser's cookie is counted over this many days (a cleared cookie does not give ten more; a shared address is not held for ever). */
    public const IP_DAYS = 30;

    /**
     * Things that are worn or have to fit something: a free design has no dimensions to trust, so the sentence under
     * it never says it will fit (cs, en, es).
     */
    private const FIT = '/[čc]elenk|n[áa]ramek|n[áa]ramk|prsten|br[ýy]l|\bp[áa]sek|\bmask|nasadit|na hlavu|na ruku|na prst|dr[žz][áa]k|\bkryt|v[íi][čc]k|pouzdr|\bobal\b|stoj[áa]n|aby (to )?(pasoval|sed[ěe]l)|headband|bracelet|\bring\b|glasses|holder|\bcase\b|\bcover\b|\blid\b|\bstand\b|to fit|diadema|pulsera|anillo|\bgafas|m[áa]scara|soporte|\bfunda|\btapa\b/u';

    /** Why the last start was refused by a count or the credit: the price and what is missing, for the sentence. */
    public ?QuotaExceeded $last = null;

    public function __construct(private readonly GenerationService $generations) {}

    /**
     * The switch, the counts, the price and the confidence a tool opens directly from: the admin's values over the config.
     * `price` is null while no price is set: nothing is sold then.
     *
     * @return array{enabled: bool, free_guest: int, free_user: int, price: ?float, per_day: int, direct_confidence: float, target_mm: int}
     */
    public static function settings(): array
    {
        $defaults = (array) config('home.intake.design');
        try {
            $over = app(FarmSettings::class)->get(self::SETTING);
        } catch (\Throwable) {
            $over = null;   // no table yet (a fresh install): the config alone
        }
        $over = is_array($over) ? $over : [];
        $confidence = (float) ($over['direct_confidence'] ?? $defaults['direct_confidence'] ?? IntakeRouter::DIRECT_CONFIDENCE);
        $price = array_key_exists('price', $over) ? $over['price'] : ($defaults['price'] ?? null);

        return [
            'enabled' => (bool) ($over['enabled'] ?? $defaults['enabled'] ?? false),
            'free_guest' => max(0, (int) ($over['free_guest'] ?? $defaults['free_guest'] ?? 10)),
            'free_user' => max(0, (int) ($over['free_user'] ?? $defaults['free_user'] ?? 10)),
            'price' => is_numeric($price) && (float) $price > 0 ? round((float) $price, 2) : null,
            'per_day' => max(0, (int) ($over['per_day'] ?? $defaults['per_day'] ?? 100)),
            'direct_confidence' => max(0.0, min(1.0, $confidence)),
            'target_mm' => max(20, min(250, (int) ($defaults['target_mm'] ?? 80))),
        ];
    }

    /** @param  array{enabled?: bool, free_guest?: int, free_user?: int, price?: mixed, per_day?: int, direct_confidence?: float}  $values */
    public static function save(array $values): void
    {
        $price = $values['price'] ?? null;
        FarmSetting::updateOrCreate(['key' => self::SETTING], ['value' => [
            'enabled' => (bool) ($values['enabled'] ?? false),
            'free_guest' => max(0, min(1000, (int) ($values['free_guest'] ?? 10))),
            'free_user' => max(0, min(1000, (int) ($values['free_user'] ?? 10))),
            'price' => is_numeric($price) && (float) $price > 0 ? round((float) $price, 2) : null,
            'per_day' => max(0, min(5000, (int) ($values['per_day'] ?? 100))),
            'direct_confidence' => round(max(0.0, min(1.0, (float) ($values['direct_confidence'] ?? IntakeRouter::DIRECT_CONFIDENCE))), 2),
        ]]);
        app()->forgetInstance(FarmSettings::class);
    }

    /** The confidence from which a chosen tool opens without a card. */
    public static function directConfidence(): float
    {
        return self::settings()['direct_confidence'];
    }

    /** Switched on by the admin, and a generator is configured at all. */
    public function enabled(): bool
    {
        return self::settings()['enabled'] && $this->generations->enabled();
    }

    /** The runs that count: started by the home page (or a change in words of such a design), not a copy, not failed. */
    private static function runs()
    {
        return GenerationRequest::whereIn('type', ['image', 'text'])->whereNotNull('description->intake')
            ->where('engine', 'not like', '%+cache')->where('status', '!=', 'failed');
    }

    /** How many free designs the home page started today, for the whole site (paid ones too: the cap is about our cost). */
    public static function startedToday(): int
    {
        return self::runs()->where('created_at', '>=', now()->startOfDay())->count();
    }

    /**
     * How many free runs this visitor has used, in all (not per day). Without an account: the runs of this browser
     * (its session cookie), or of this address in the last IP_DAYS days, whichever is more. With an account: the
     * account's own runs plus those of the browser it is signed in from, so what was used before signing in still
     * counts and signing in adds `free_user` on top. Paid runs are not counted.
     */
    public static function used(?string $ip, ?int $sessionId, ?int $userId): int
    {
        $free = fn () => self::runs()->where(fn ($q) => $q->whereNull('paid_credit')->orWhere('paid_credit', '<=', 0));
        if ($userId) {
            return $free()->where(fn ($q) => $q->where('owner_user_id', $userId)
                ->when($sessionId, fn ($q) => $q->orWhere(fn ($q) => $q->whereNull('owner_user_id')->where('anonymous_session_id', $sessionId))))->count();
        }
        $guest = fn () => $free()->whereNull('owner_user_id');

        return max(
            $sessionId ? $guest()->where('anonymous_session_id', $sessionId)->count() : 0,
            $ip ? $guest()->where('ip', $ip)->where('created_at', '>=', now()->subDays(self::IP_DAYS))->count() : 0,
        );
    }

    /** How many free runs this visitor has in all: without an account `free_guest`, with one `free_user` more. */
    public static function allowance(?User $user): int
    {
        $set = self::settings();

        return $set['free_guest'] + ($user ? $set['free_user'] : 0);
    }

    /**
     * May this run start? Null = yes, for free. Otherwise the reason: the day's cap, the visitor's free runs used up
     * (a guest is told to sign in; an account is told the price, or that there is no further run while none is set).
     * The admin tries freely. Nothing is charged here.
     */
    public static function limit(?string $ip, ?int $sessionId, ?User $user): ?string
    {
        if ($user?->isAdmin()) {
            return null;
        }
        $set = self::settings();
        if (self::startedToday() >= $set['per_day']) {
            return self::DAY_LIMIT;
        }
        if (self::used($ip, $sessionId, $user?->id) < self::allowance($user)) {
            return null;
        }
        if (! $user) {
            return self::GUEST_LIMIT;
        }

        return $set['price'] !== null && config('farm.enabled') ? self::PAY : self::USER_LIMIT;
    }

    /**
     * The gate every run of the generator marked `description.intake` goes through (GenerationService): free while the
     * counts allow; a paid run only with `$pay` (the visitor's own press on a button that names the price), charged
     * from the credit here.
     *
     * @throws QuotaExceeded
     */
    public static function admit(GenerationRequest $req, bool $pay): void
    {
        $user = $req->owner_user_id ? User::find($req->owner_user_id) : null;
        $reason = self::limit($req->ip, $req->anonymous_session_id, $user);
        if ($reason === null) {
            return;
        }
        $set = self::settings();
        $price = (float) ($set['price'] ?? 0);
        if ($reason !== self::PAY || ! $pay || ! $user) {
            throw new QuotaExceeded($reason, $reason === self::DAY_LIMIT ? $set['per_day'] : self::allowance($user), $price);
        }
        try {
            $paid = app(Wallet::class)->charge($user, $price, 'design '.$req->token);
        } catch (InsufficientCredit $e) {
            throw new QuotaExceeded(self::CREDIT, self::allowance($user), $e->needed, $e->missing(), $e->currency);
        }
        $req->paid_credit = abs((float) $paid->amount);
    }

    /** The sentence for a reason, in the visitor's language, with the counts and the price in it. */
    public static function sentence(string $reason, string $locale, ?QuotaExceeded $e = null): string
    {
        $set = self::settings();

        return IntakeRouter::sentence('router.design.'.$reason, $locale, [
            'n' => $set['free_guest'], 'm' => $set['free_user'],
            'price' => $e && $e->price > 0 ? Money::of($e->price, $e->currency)->format() : ($set['price'] !== null ? Money::czk($set['price'])->format() : ''),
            'missing' => $e ? Money::of($e->missing, $e->currency)->format() : '',
        ]);
    }

    /**
     * Why a free design cannot be started for this intake now; null when it can (for free, or paid when `$pay`).
     */
    public function refusal(Intake $intake, ?string $ip, ?AnonymousSession $session, ?User $user, bool $pay = false): ?string
    {
        if (! $this->enabled()) {
            return self::OFF;
        }
        // a closed farm takes no orders from this visitor: nothing is generated for a thing that cannot be ordered
        if (FarmState::isClosed() && ! FarmGate::offered($user)) {
            return self::CLOSED;
        }
        if (self::prompt($intake) === null && self::photoFor($intake) === null) {
            return $intake->hasPhoto() && ($intake->answer['photo'] ?? null) === 'person' ? self::PERSON : self::WORDS;
        }
        $reason = self::limit($ip, $session?->id, $user);

        return $reason === self::PAY && $pay ? null : $reason;
    }

    /**
     * What the generator is asked for: the thing in plain English when the model wrote it, else the visitor's own
     * words. Null when there is too little to make anything of (one short word).
     */
    public static function prompt(Intake $intake): ?string
    {
        $english = trim((string) ($intake->answer['prompt_en'] ?? ''));
        if (mb_strlen($english) >= 6) {
            return mb_substr($english, 0, 300);
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $intake->text));
        $letters = (int) preg_match_all('/\p{L}/u', $text);
        $words = (int) preg_match_all('/\p{L}{2,}/u', $text);

        return $letters >= 6 && $words >= 2 ? mb_substr($text, 0, 300) : null;
    }

    /**
     * The photo a design may be made from: only when the model said it shows the thing itself or an animal. A person
     * in the photo goes to the bust's own page, with its consent; a flat picture goes to the tools for pictures.
     */
    public static function photoFor(Intake $intake): ?string
    {
        return $intake->hasPhoto() && in_array($intake->answer['photo'] ?? null, ['object', 'animal'], true) ? $intake->picturePath() : null;
    }

    /**
     * Does this decision go on to a free design by itself? A shape nobody has a template for; a tool the assistant was
     * not sure about; words the keywords could make nothing of. A part with dimensions and anything else the model
     * handed to a person stay with a person (the page still offers the design as a button, said honestly).
     */
    public static function wanted(Intake $intake): bool
    {
        if ($intake->route === Intake::ROUTE_TOOL) {
            return (float) $intake->confidence < self::directConfidence();
        }
        if ($intake->route !== Intake::ROUTE_HUMAN || ! empty($intake->answer['failed'])) {
            return false;
        }

        return $intake->wants_shape || ($intake->model === IntakeRouter::ENGINE_KEYWORDS && ! IntakeRouter::technical($intake->text));
    }

    /** True for a thing that is worn or has to fit: the page then says that nobody knows whether it will. */
    public static function mustFit(?string $text): bool
    {
        return (bool) preg_match(self::FIT, mb_strtolower((string) $text));
    }

    /**
     * Starts the design and writes it on the intake: the route becomes `design`, `answer.design` keeps the generation,
     * how it was started and what the decision was before. Returns null when it was started, else the reason.
     * `$pay` = the visitor pressed the button that names the price.
     *
     * @param  'auto'|'button'  $by
     */
    public function start(Intake $intake, string $by, ?string $ip, ?AnonymousSession $session, ?User $user, bool $pay = false): ?string
    {
        if ($reason = $this->refusal($intake, $ip, $session, $user, $pay)) {
            return $reason;
        }
        $photo = self::photoFor($intake);
        $prompt = self::prompt($intake) ?? 'object';
        $copy = null;
        if ($photo !== null) {
            $disk = Storage::disk(IntakeService::DISK);
            // the generator screens what the bust's page screens: a refused photo is not sent, the words alone are used
            $check = app(VisionDescriber::class)->moderate($disk->path($photo));
            if ($check['ok']) {
                // a copy for the run: the job deletes it when the model is made, the intake keeps its own for 30 days
                $copy = 'photos/figures/'.Str::uuid().'.jpg';
                $disk->copy($photo, $copy);
            } else {
                Log::info('intake design photo rejected', ['intake' => $intake->id, 'reason' => $check['reason']]);
                if (self::prompt($intake) === null) {
                    return self::WORDS;
                }
            }
        }
        $size = (int) ($intake->params['size_mm'] ?? 0);
        try {
            $generation = $this->generations->fromIntake($intake->token, $prompt, $copy, $size >= 20 && $size <= 250 ? $size : self::settings()['target_mm'], $ip, $session, $user, $pay);
        } catch (QuotaExceeded $e) {
            if ($copy !== null) {
                Storage::disk(IntakeService::DISK)->delete($copy);
            }
            $this->last = $e;

            return $e->reason;
        }
        $was = ['route' => $intake->route, 'tool_key' => $intake->tool_key, 'wants_shape' => (bool) $intake->wants_shape, 'summary' => (string) $intake->summary];
        $answer = (array) $intake->answer;
        unset($answer['design_refused']);
        $intake->forceFill([
            'route' => Intake::ROUTE_DESIGN,
            'tool_key' => null,
            'wants_shape' => false,
            'summary' => IntakeRouter::sentence('router.design.summary', $intake->locale).(self::mustFit($intake->text) ? ' '.IntakeRouter::sentence('router.design.fit', $intake->locale) : ''),
            'answer' => ['design' => ['generation' => $generation->token, 'by' => $by, 'from' => $copy !== null ? 'photo' : 'text', 'prompt' => $prompt, 'paid' => (float) $generation->paid_credit, 'at' => now()->toIso8601String(), 'was' => $was]] + $answer,
        ])->save();
        Track::event('intake_designed', $intake, ['code' => $by, 'kind' => $copy !== null ? 'photo' : 'text', 'from' => (string) ($was['wants_shape'] ? Intake::ROUTE_SHAPE : $was['route'])]);

        return null;
    }
}
