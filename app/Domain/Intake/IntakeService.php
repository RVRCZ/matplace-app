<?php

namespace App\Domain\Intake;

use App\Domain\Farm\OrderFlow;
use App\Engines\Converter\ConverterChain;
use App\Engines\Repair\PythonTool;
use App\Http\Controllers\Api\UploadController;
use App\Mail\IntakeReceived;
use App\Models\Intake;
use App\Models\IntakeRequest;
use App\Support\Money;
use App\Support\ToolSeo;
use App\Support\Track;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The router's intake (TODO #78, docs/AN.md §22): what the home page posts is checked and stored, a photo is made
 * upright and small enough for the model (HEIC from an iPhone becomes a JPEG), a 3D file goes the calculator's own way,
 * the rest is decided by IntakeRouter. "A person takes it over" leaves an e-mail and tells the operator. The files of
 * an intake live on the local disk under intake/<token>/ and go after 30 days (prunePhotos, matplace:prune).
 */
final class IntakeService
{
    public const PHOTO_MB = 15;

    public const TEXT_MAX = 2000;

    /** The long edge of the picture the model and the tool pages get. */
    public const PHOTO_EDGE = 2000;

    public const PHOTO_EXT = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];

    public const DISK = 'local';

    public function __construct(private readonly IntakeRouter $router, private readonly PythonTool $python, private readonly ConverterChain $converters, private readonly IntakeDesign $design) {}

    /**
     * POST /intake: a photo, a 3D file or words (at least one), stored and decided.
     *
     * @throws ValidationException
     */
    public function create(Request $request): Intake
    {
        // bytes that are no UTF-8 (a form posted from an odd encoding) would fail far later, in the database: a sentence instead (round 2)
        if (is_string($request->input('text')) && ! mb_check_encoding($request->input('text'), 'UTF-8')) {
            throw ValidationException::withMessages(['text' => __('router.text_invalid')]);
        }
        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:'.self::TEXT_MAX],
            'photo' => ['nullable', 'file', 'max:'.(self::PHOTO_MB * 1024)],
            'model' => ['nullable', 'file', 'max:'.((int) config('uploads.max_mb', 120) * 1024)],
        ], [
            'photo.max' => __('photo.too_big', ['mb' => self::PHOTO_MB]), 'photo.uploaded' => __('photo.too_big', ['mb' => self::PHOTO_MB]),
            'model.max' => __('calc.error.too_big', ['max' => (int) config('uploads.max_mb', 120)]), 'model.uploaded' => __('calc.error.too_big', ['max' => (int) config('uploads.max_mb', 120)]),
        ]);
        $text = trim((string) ($data['text'] ?? ''));
        $photo = $request->file('photo');
        $model = $request->file('model');
        if (! $photo && ! $model && $text === '') {
            throw ValidationException::withMessages(['text' => __('router.need_input')]);
        }
        if ($photo && ! in_array(strtolower($photo->getClientOriginalExtension()), self::PHOTO_EXT, true) && ! str_starts_with((string) $photo->getMimeType(), 'image/')) {
            throw ValidationException::withMessages(['photo' => __('photo.not_image')]);
        }
        $formats = $this->converters->inputFormats();
        if ($model && ! in_array(strtolower($model->getClientOriginalExtension()), $formats, true)) {
            throw ValidationException::withMessages(['model' => __('upload.unsupported', ['formats' => strtoupper(implode(', ', $formats))])]);
        }

        $intake = Intake::create([
            'token' => Intake::newToken(),
            'anonymous_session_id' => $request->attributes->get('anon_session')?->id,
            'user_id' => $request->user()?->id,
            'kind' => $model ? Intake::KIND_FILE : ($photo ? Intake::KIND_PHOTO : Intake::KIND_TEXT),
            'text' => $text !== '' ? $text : null,
            'locale' => app()->getLocale(),
            'status' => Intake::STATUS_NEW,
        ]);
        if ($photo) {
            $this->storePhoto($intake, $photo);
        }
        // what the visitor began with: words, words and a photo, a photo, a file; and the example they clicked, changed or not (§23.4)
        $how = ['kind' => $intake->kind, 'has_text' => $text !== '', 'code' => $model ? 'file' : ($photo ? ($text !== '' ? 'text_photo' : 'photo') : 'text')];

        // advertising stops here, before the model costs anything (IntakeSpam, config/home.php): stored, answered neutrally, the admin can give it back
        if ($held = IntakeSpam::check($request, $text, (bool) $photo, (bool) $model)) {
            $intake->forceFill([
                'status' => Intake::STATUS_SPAM,
                'summary' => __($held['reply'] === 'price' ? 'router.price_only' : 'router.held'),
                'answer' => ['spam' => ['rules' => $held['rules'], 'points' => $held['points'], 'reply' => $held['reply'], 'at' => now()->toIso8601String()]],
            ])->save();
            Track::event('intake_spam', $intake, ['kind' => $intake->kind, 'code' => $held['rules'][0] ?? 'rules']);

            return $intake;
        }
        $hint = (string) $request->input('hint');
        if (preg_match('/^[a-z_]{1,24}\.[1-9]$/', $hint)) {
            [$event, $n] = explode('.', $hint);
            $example = IntakeHints::examples($event, app()->getLocale())[(int) $n - 1]['fill'] ?? null;
            $how += ['hint' => $hint, 'edited' => $example === null || trim($example) !== $text];
        }
        Track::event('intake_submitted', $intake, $how);

        if ($model) {
            // the calculator's own way in: the file is a ModelFile like any upload, the words stay with the intake (docs/AN.md §22 F)
            $file = UploadController::accept($model, $request, $this->converters);
            $intake->fill([
                'model_file_id' => $file->id, 'route' => Intake::ROUTE_FILE, 'confidence' => 1.0,
                'summary' => __('router.summary.file', ['name' => $file->original_name]),
            ])->save();
            Track::event('intake_file_to_calc', $intake, ['kind' => $file->ext]);

            return $intake;
        }

        $this->router->route($intake);
        Track::event('intake_routed', $intake, array_filter([
            'route' => $intake->route, 'tool' => $intake->tool_key, 'confidence' => (float) $intake->confidence,
            'ms' => (int) $intake->duration_ms, 'tokens' => (int) $intake->tokens_in + (int) $intake->tokens_out,
        ], fn ($v) => $v !== null));

        // no tool, or a tool the assistant was not sure about: a free design is started right away, when that is switched
        // on and the visitor's and the day's counts allow it (IntakeDesign, docs/AN.md §24.4); why not is kept for the page
        if (IntakeDesign::wanted($intake)) {
            $refused = $this->design->start($intake, 'auto', $request->ip(), $request->attributes->get('anon_session'), $request->user());
            if ($refused !== null) {
                $intake->forceFill(['answer' => ['design_refused' => $refused] + (array) $intake->answer])->save();
            }
        }

        return $intake;
    }

    /**
     * "Try a free design": the visitor's own press where a person was offered, or after "I wanted something else"
     * (docs/AN.md §24.4). An intake that has its design already changes nothing.
     *
     * @throws ValidationException
     */
    public function startDesign(Intake $intake, Request $request): Intake
    {
        if ($intake->route === Intake::ROUTE_FILE || $intake->status === Intake::STATUS_SPAM) {
            throw ValidationException::withMessages(['route' => __('router.error')]);
        }
        if ($intake->route === Intake::ROUTE_DESIGN) {
            return $intake;
        }
        // `pay` = the press on the button that names the price (an account past its free designs, a price being set)
        $refused = $this->design->start($intake, 'button', $request->ip(), $request->attributes->get('anon_session'), $request->user(), $request->boolean('pay'));
        if ($refused !== null) {
            $intake->forceFill(['answer' => ['design_refused' => $refused] + (array) $intake->answer])->save();
            throw ValidationException::withMessages(['route' => IntakeDesign::sentence($refused, $intake->locale, $this->design->last)]);
        }

        return $intake;
    }

    /**
     * The photo as uploaded, and next to it the picture for the model: upright (EXIF), the long edge at most 2000 px,
     * a JPEG whatever came in (HEIC through pillow_heif). When Python cannot help, a JPEG, PNG or WebP goes as it is;
     * anything else is refused as unreadable.
     *
     * @throws ValidationException
     */
    private function storePhoto(Intake $intake, UploadedFile $photo): void
    {
        $disk = Storage::disk(self::DISK);
        $ext = strtolower($photo->getClientOriginalExtension()) ?: 'jpg';
        $original = $intake->dir().'/original.'.$ext;
        $disk->putFileAs($intake->dir(), $photo, 'original.'.$ext);
        $model = $intake->dir().'/model.jpg';
        $made = false;
        try {
            $r = $this->python->runScript('photo_tool.py', ['normalize', $disk->path($original), $disk->path($model), (string) self::PHOTO_EDGE], 60);
            $made = ! empty($r['ok']) && $disk->exists($model);
        } catch (\Throwable $e) {
            Log::warning('Intake photo normalize failed', ['intake' => $intake->id, 'error' => mb_substr($e->getMessage(), 0, 300)]);
        }
        if (! $made) {
            $mime = @getimagesize($disk->path($original))['mime'] ?? null;
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                $disk->deleteDirectory($intake->dir());
                $intake->delete();
                throw ValidationException::withMessages(['photo' => __('router.photo_unreadable')]);
            }
        }
        $intake->forceFill(['photo_path' => $original, 'photo_model_path' => $made ? $model : null])->save();
    }

    /**
     * "Try it now without a designer" (round 2, docs/AN.md §22.11): an intake that ended with a person (or a shape) is
     * decided again with both ruled out; the earlier decision stays in `answer.history`. An intake already at a tool
     * changes nothing; a file has no designer to skip.
     *
     * @throws ValidationException
     */
    public function reroute(Intake $intake): Intake
    {
        if ($intake->route === Intake::ROUTE_FILE || $intake->status === Intake::STATUS_SPAM) {
            throw ValidationException::withMessages(['route' => __('router.error')]);
        }
        if ($intake->route === Intake::ROUTE_TOOL && ! $intake->wants_shape) {
            return $intake;
        }
        $from = $intake->wants_shape ? Intake::ROUTE_SHAPE : (string) $intake->route;
        $this->router->reroute($intake);
        Track::event('intake_rerouted', $intake, array_filter([
            'from' => $from, 'to' => $intake->route, 'tool' => $intake->tool_key, 'confidence' => (float) $intake->confidence, 'ms' => (int) $intake->duration_ms,
        ], fn ($v) => $v !== null));

        return $intake;
    }

    /**
     * "A person takes it over": the customer's e-mail and consent, a confirmation to them, a mail to the operator
     * (switch `notify_inquiry` in the farm's settings).
     *
     * @param  array{email: string, name?: ?string, phone?: ?string}  $data
     */
    public function requestHuman(Intake $intake, array $data): IntakeRequest
    {
        $request = IntakeRequest::updateOrCreate(['intake_id' => $intake->id], [
            'email' => strtolower(trim($data['email'])),
            'name' => isset($data['name']) ? mb_substr(trim((string) $data['name']), 0, 120) : null,
            'phone' => isset($data['phone']) ? mb_substr(trim((string) $data['phone']), 0, 40) : null,
            'consent_at' => now(),
            'wants_shape' => (bool) $intake->wants_shape,
            'status' => IntakeRequest::STATUS_NEW,
            'locale' => $intake->locale,
        ]);
        $intake->forceFill(['status' => Intake::STATUS_HUMAN])->save();
        Track::event('intake_human_created', $intake, ['wants_shape' => (bool) $intake->wants_shape]);

        $hours = IntakePage::hours();
        try {
            Mail::to($request->email)->locale($intake->locale)->queue(new IntakeReceived($intake, $request, $hours));
        } catch (\Throwable $e) {
            Log::warning('Intake confirmation mail failed', ['intake' => $intake->id, 'error' => $e->getMessage()]);
        }
        $lines = array_filter([
            $request->email,
            $intake->text ? mb_substr($intake->text, 0, 300) : null,
            __('farm.admin.mail.intake_line', ['kind' => $intake->kind, 'route' => (string) $intake->route, 'confidence' => number_format((float) $intake->confidence, 2)], 'cs'),
            $intake->wants_shape ? __('farm.admin.mail.intake_shape', [], 'cs') : null,
        ]);
        app(OrderFlow::class)->alertAdmin(__('farm.admin.mail.intake', ['what' => mb_substr($intake->text ?: __('router.kind.photo', [], 'cs'), 0, 60)], 'cs'), null, array_values($lines), 'inquiry', null, route('admin.intake.show', $intake));

        return $request;
    }

    /**
     * The admin's hand over the rules (docs/AN.md §23.2). `spam` = true holds a wish as advertising (it leaves the
     * default list); false gives a held wish back: one that was never decided is decided now, by the model, as if it
     * had just come in.
     */
    public function markSpam(Intake $intake, bool $spam): Intake
    {
        if ($spam) {
            $intake->forceFill(['status' => Intake::STATUS_SPAM, 'answer' => ['spam' => ['rules' => [IntakeSpam::RULE_MANUAL], 'points' => 0, 'reply' => 'held', 'at' => now()->toIso8601String()]] + (array) $intake->answer])->save();

            return $intake;
        }
        if ($intake->status !== Intake::STATUS_SPAM) {
            return $intake;
        }
        $answer = (array) $intake->answer;
        $was = $answer['spam'] ?? null;
        unset($answer['spam']);
        $decided = $intake->route !== null;   // held by hand after a decision: the decision is still there
        $intake->forceFill(['status' => $intake->request ? Intake::STATUS_HUMAN : Intake::STATUS_NEW, 'answer' => $answer ?: null])->save();
        if (! $decided && $intake->kind !== Intake::KIND_FILE) {
            $this->router->route($intake);
        }
        $intake->forceFill(['answer' => (array) $intake->answer + ['released' => ['at' => now()->toIso8601String(), 'was' => $was]]])->save();

        return $intake;
    }

    /**
     * What the page gets back: the route, the sentence, the parameters and where each way on leads.
     *
     * @return array<string, mixed>
     */
    public function describe(Intake $intake, bool $again = false): array
    {
        $tool = $intake->route === Intake::ROUTE_TOOL && $intake->tool_key ? $intake->tool_key : null;
        $toolUrl = fn (string $key): string => ToolSeo::url($key).'?'.http_build_query(['intake' => $intake->token] + (IntakeRouter::TOOLS[$key]['query'] ?? []));
        $generation = $intake->route === Intake::ROUTE_DESIGN ? ($intake->answer['design']['generation'] ?? null) : null;
        $designUrl = $generation ? route('home').'?'.http_build_query(['design' => $generation, 'wish' => $intake->token]) : null;
        // where a free design could still be started by a press: what stands in its way for this visitor right now (their
        // free designs used up, the day's cap), so signing in or a price set meanwhile shows at once
        $canDesign = ! $generation && $intake->route !== Intake::ROUTE_FILE && $intake->status !== Intake::STATUS_SPAM && $this->design->enabled() && IntakeDesign::prompt($intake) !== null;
        $limit = $canDesign ? IntakeDesign::limit(request()->ip(), request()->attributes->get('anon_session')?->id, request()->user()) : null;
        $file = $intake->route === Intake::ROUTE_FILE ? $intake->modelFile : null;
        $calc = $file ? route('home').'?'.http_build_query(array_filter(['open' => $file->uuid, 'note' => $intake->text])) : null;
        $rerouted = ($intake->answer['origin'] ?? null) === IntakeRouter::ORIGIN_REROUTE;
        // held by the rules against advertising: a sentence and nothing to press (`held` = neutral, `price` = the answer about prices)
        $held = $intake->status === Intake::STATUS_SPAM ? (string) ($intake->answer['spam']['reply'] ?? 'held') : null;

        // one press of "Vytvořit" ends on a design (docs/AN.md §24): the tool's page, the free design or the calculator with
        // the file opens by itself; a card is shown only where there is nothing to open (a person, a held wish) and on
        // the way back ("I wanted something else")
        $go = $held || $again ? null : ($tool ? $toolUrl($tool) : ($designUrl ?? $calc));
        // the other ways on the card: the tools that would also do (the chosen one too, on the way back)
        $others = array_values(array_unique(array_filter(array_merge($again && $tool ? [$tool] : [], (array) ($intake->answer['alternatives'] ?? []), $again && $intake->route === Intake::ROUTE_DESIGN ? (array) ($intake->answer['design']['was']['tool_key'] ?? []) : []),
            fn ($key) => is_string($key) && isset(IntakeRouter::TOOLS[$key]) && ($intake->hasPhoto() || ! in_array($key, ['figure', 'pet'], true)))));

        return [
            'token' => $intake->token,
            'route' => $intake->route,
            'go' => $go,
            'again' => $again,
            'text' => $again ? (string) $intake->text : null,
            // a thing that is worn or has to fit: the design's page says that nobody knows whether it will
            'fit' => $intake->route === Intake::ROUTE_DESIGN && IntakeDesign::mustFit($intake->text),
            'unsure' => $tool !== null && (float) $intake->confidence < IntakeDesign::directConfidence(),
            'others' => array_map(fn (string $key) => ['key' => $key, 'title' => __('tools.'.$key.'.title'), 'url' => $toolUrl($key)], $others),
            // a free design that was wanted and could not be started for a count (the switch being off is no news to the visitor)
            'design_note' => $limit !== null ? IntakeDesign::sentence($limit, $intake->locale) : null,
            // a visitor without an account who used their free designs: signing in gives more
            'login' => $limit === IntakeDesign::GUEST_LIMIT ? route('login') : null,
            'held' => $held,
            'tool_key' => $tool,
            'tool' => $tool ? __('tools.'.$tool.'.title') : null,
            'summary' => (string) $intake->summary,
            'params' => (array) $intake->params,
            'questions' => array_values((array) ($intake->answer['questions'] ?? [])),
            'alternatives' => array_values((array) ($intake->answer['alternatives'] ?? [])),
            'wants_shape' => (bool) $intake->wants_shape,
            'has_photo' => $intake->hasPhoto(),
            'rerouted' => $rerouted,
            'hours' => IntakePage::hours(),
            'file' => $file ? ['uuid' => $file->uuid, 'name' => $file->original_name] : null,
            'actions' => [
                // the key ring opens its quick form (?form=1), the one with a picture field; the rest their plain page
                'tool' => $tool ? $toolUrl($tool) : null,
                'calc' => $calc,
                // the free design that is on its way (or done), and the press that starts one where none was started
                'design' => $designUrl,
                'start_design' => $canDesign && $limit === null ? route('intake.design', $intake) : null,
                // past the free designs with a price set: the same press, paid from the credit, on a button that names the price
                'pay_design' => $limit === IntakeDesign::PAY
                    ? ['url' => route('intake.design', $intake), 'price' => Money::czk((float) IntakeDesign::settings()['price'])->format(), 'topup' => route('account.credit')] : null,
                'request' => $held ? null : route('intake.request', $intake),
                // "try it now without a designer": only where a person was offered, and only once
                'reroute' => ! $held && $intake->route === Intake::ROUTE_HUMAN && ! $rerouted ? route('intake.reroute', $intake) : null,
            ],
        ];
    }

    /**
     * A tool's page opened with ?intake=<token>: what its scripts get (the photo's address, the parameters), and the
     * intake is marked as opened the first time.
     *
     * @return array<string, mixed>
     */
    public function handoff(Intake $intake, string $tool): array
    {
        if ($intake->status === Intake::STATUS_NEW) {
            $intake->forceFill(['status' => Intake::STATUS_OPENED, 'opened_at' => now()])->save();
            Track::event('intake_tool_opened', $intake, ['tool' => $tool]);
        }

        // the parameters as this tool takes them (another tool than the chosen one may be opened from "I wanted something else")
        $params = IntakePrefill::complete((array) $intake->params, $intake->text);
        $values = IntakePrefill::values($tool, $params);
        $locale = app()->getLocale();

        return [
            'token' => $intake->token,
            'tool' => $tool,
            // a tool of words alone (a sign, standing letters) gets no photo, whatever came in
            'file' => $intake->hasPhoto() && (IntakeRouter::TOOLS[$tool]['input'] ?? 'photo') !== 'text' ? route('intake.file', $intake) : null,
            'name' => $intake->photo_path ? basename($intake->photo_path) : null,
            'params' => $params,
            'text' => $intake->text,
            // the form's own values (IntakePrefill, docs/AN.md §24.3) and the page's one line: what the assistant understood, and the way back
            'values' => $values,
            'understood' => IntakePrefill::understood($tool, $params, $values, $intake->hasPhoto(), $locale),
            'unsure' => $intake->tool_key === $tool && (float) $intake->confidence < IntakeDesign::directConfidence(),
            'back' => route('home').'?'.http_build_query(['wish' => $intake->token]),
            'words' => ['lead' => __('router.got.lead'), 'unsure' => __('router.got.unsure'), 'nothing' => __('router.got.nothing'), 'back' => __('router.got.back')],
        ];
    }

    /** Photos older than Intake::PHOTO_DAYS leave the disk (matplace:prune); the rows stay. Returns how many intakes lost theirs. */
    public function prunePhotos(bool $dry = false): int
    {
        $n = 0;
        $disk = Storage::disk(self::DISK);
        Intake::whereNull('photos_pruned_at')->where(fn ($q) => $q->whereNotNull('photo_path')->orWhereNotNull('photo_model_path'))
            ->where('created_at', '<', now()->subDays(Intake::PHOTO_DAYS))->each(function (Intake $intake) use (&$n, $dry, $disk) {
                $n++;
                if ($dry) {
                    return;
                }
                $disk->deleteDirectory($intake->dir());
                $intake->forceFill(['photos_pruned_at' => now()])->save();
            });

        return $n;
    }
}
