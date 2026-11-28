<?php

namespace App\Domain\Stats;

use App\Http\Controllers\Api\StepController;
use App\Models\Event;
use App\Models\FarmOrder;
use App\Models\FarmOrderEvent;
use App\Models\Intake;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * "Aktivita" in the farm's admin (Roman, 28 Nov 2026): what happened on the web, newest first, in one stream: models
 * uploaded and generated, the way to an order (print proposal, recalculation, delivery, payment), the farm's prints
 * (started, done, stopped) and the requests of the home page's router. Built from our own `events` (App\Support\Track:
 * the staff and test runs are never written there) and the orders' status history (`farm_order_events`).
 *
 * Robots open the order page by the thousand (`order_started` of sessions no browser ever confirmed, 28 Nov 2026:
 * 2 354 in two days, every other kind of event a few dozen), so the types a mere page load writes come in only from
 * sessions a person confirmed (`meta.js` / `meta.act` on the visit); what needs a click or an upload comes in as it is.
 */
class ActivityStream
{
    public const GROUPS = ['model' => 'Modely', 'order' => 'Objednávky', 'print' => 'Tisk', 'intake' => 'Dotazy', 'visit' => 'Příchody'];

    public const WINDOWS = ['1h' => 1, '6h' => 6, '24h' => 24, '3d' => 72, '7d' => 168];

    public const LIMIT = 400;

    /** Types a page load writes: shown only for sessions a person confirmed. */
    private const LOADS = ['order_started', 'intake_view', Event::VISIT];

    /**
     * event type => [group, label, tone, only in the detailed view]. Tone: ok (green), warn (amber), bad (red), info, muted.
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    private const EVENTS = [
        Event::VISIT => ['visit', 'Přišel na web', 'muted', true],
        'search' => ['visit', 'Hledal v katalogu', 'muted', true],
        'register' => ['visit', 'Založil si účet', 'ok', false],
        'upload' => ['model', 'Nahrál model', 'info', false],
        'calculation' => ['model', 'Spočítal cenu v kalkulačce', 'info', false],
        'generate' => ['model', 'Spustil generování modelu', 'info', false],
        'model_ready' => ['model', 'Model je hotový', 'ok', false],
        'notify' => ['model', 'Chce e-mail, až bude model hotový', 'info', false],
        'download' => ['model', 'Stáhl model', 'info', false],
        'share' => ['model', 'Sdílel model', 'info', true],
        'failed' => ['model', 'Chyba', 'bad', false],
        'order_started' => ['order', 'Otevřel návrh tisku', 'info', false],
        'order_created' => ['order', 'Založil zakázku (návrh tisku)', 'info', false],
        'price_shown' => ['order', 'Uviděl přesnou cenu', 'info', false],
        'checkout_started' => ['order', 'Šel platit', 'warn', false],
        'order_paid' => ['order', 'Zaplatil', 'ok', false],
        'topup_started' => ['order', 'Dobíjí kredit', 'info', false],
        'topup' => ['order', 'Dobil kredit', 'ok', false],
        'intake_view' => ['intake', 'Otevřel rozcestník', 'muted', true],
        'intake_chip' => ['intake', 'Klikl na příklad v rozcestníku', 'muted', true],
        'intake_photo' => ['intake', 'Přidal fotku do rozcestníku', 'info', true],
        'intake_voice' => ['intake', 'Diktoval do rozcestníku', 'info', true],
        'intake_again' => ['intake', 'Vrátil se: chtěl něco jiného', 'warn', false],
        'intake_submitted' => ['intake', 'Napsal, co chce (rozcestník)', 'info', false],
        'intake_routed' => ['intake', 'Rozcestník ho poslal k nástroji', 'muted', true],
        'intake_rerouted' => ['intake', 'Rozcestník ho poslal jinam', 'muted', true],
        'intake_tool_opened' => ['intake', 'Otevřel doporučený nástroj', 'info', false],
        'intake_designed' => ['intake', 'Rozcestník mu navrhl model', 'ok', false],
        'intake_file_to_calc' => ['intake', 'Rozcestník ho poslal do kalkulačky', 'muted', true],
        'intake_human_created' => ['intake', 'Dotaz pro nás (odpovídá člověk)', 'warn', false],
        'intake_spam' => ['intake', 'Spam v rozcestníku', 'muted', true],
    ];

    /** The page's own steps (`tool_step`, StepController): step => [group, label, tone, only in the detailed view]. */
    private const STEPS = [
        'scroll' => ['model', 'Posunul stránku nástroje', 'muted', true],
        'pick' => ['model', 'Otevřel výběr fotky', 'muted', true],
        'thumb' => ['model', 'Vidí náhled fotky', 'muted', true],
        'photo' => ['model', 'Vybral fotku', 'info', false],
        'photo_off' => ['model', 'Odebral fotku', 'muted', true],
        'no_photo' => ['model', 'Klikl na Vytvořit bez fotky', 'warn', false],
        'create_seen' => ['model', 'Vidí tlačítko Vytvořit', 'muted', true],
        'consent_seen' => ['model', 'Vidí souhlas', 'muted', true],
        'consent_on' => ['model', 'Zaškrtl souhlas', 'muted', true],
        'no_consent' => ['model', 'Chybí mu souhlas', 'warn', false],
        'create' => ['model', 'Klikl na Vytvořit', 'muted', true],
        'limit' => ['model', 'Narazil na denní limit', 'bad', false],
        'rejected' => ['model', 'Fotka odmítnuta', 'bad', false],
        'error' => ['model', 'Generování selhalo', 'bad', false],
        'wait_left' => ['model', 'Odešel během čekání na model', 'warn', false],
        'shown' => ['model', 'Vidí hotový model', 'muted', true],
        'model_touch' => ['model', 'Otáčí modelem', 'muted', true],
        'cta_seen' => ['model', 'Vidí tlačítko k tisku', 'muted', true],
        'price_seen' => ['model', 'Vidí cenu u modelu', 'muted', true],
        'again' => ['model', 'Zkouší to znovu', 'info', false],
        'dl_click' => ['model', 'Klikl na stažení', 'muted', true],
        'model_left' => ['model', 'Odešel od hotového modelu', 'warn', false],
        'o_color' => ['order', 'Mění barvu', 'info', false],
        'o_setting' => ['order', 'Mění nastavení tisku', 'info', true],
        'o_slice' => ['order', 'Přepočet tisku', 'info', false],
        'o_delivery' => ['order', 'Vybral doručení', 'info', false],
        'o_email' => ['order', 'Vyplnil e-mail', 'info', false],
        'o_terms' => ['order', 'Odsouhlasil podmínky', 'muted', true],
        'o_blocked' => ['order', 'Zaplatit nešlo: něco chybí', 'warn', false],
        'o_pay' => ['order', 'Klikl na Zaplatit', 'muted', true],
        'o_left' => ['order', 'Odešel ze stránky objednávky', 'warn', false],
        'img_seen' => ['model', 'Otevřel okno pro obrázek', 'muted', true],
        'img_pick' => ['model', 'Vybral obrázek', 'muted', true],
        'img_camera' => ['model', 'Fotí mobilem', 'muted', true],
        'img_close' => ['model', 'Zavřel okno bez obrázku', 'muted', true],
        'img_qty' => ['model', 'Mění počet kusů', 'muted', true],
    ];

    /** farm_order_events.to => [label, tone, only in the detailed view]; all of them in the group "print". */
    private const STATUSES = [
        FarmOrder::STATUS_AWAITING_CHOICES => ['Zakázka čeká na volby zákazníka', 'muted', true],
        FarmOrder::STATUS_UPLOADED => ['Zakázka se připravuje (slicer)', 'muted', true],
        FarmOrder::STATUS_SLICED => ['Tisk spočítán, cena je hotová', 'muted', false],
        FarmOrder::STATUS_PAID => ['Zakázka zaplacena', 'ok', false],
        FarmOrder::STATUS_QUEUED => ['Zařazeno do fronty', 'info', false],
        FarmOrder::STATUS_PRINTING => ['Tisk spuštěn', 'ok', false],
        FarmOrder::STATUS_DONE => ['Dotištěno', 'ok', false],
        FarmOrder::STATUS_HANDED_OVER => ['Předáno / odesláno', 'ok', false],
        FarmOrder::STATUS_FAILED => ['Tisk selhal', 'bad', false],
        FarmOrder::STATUS_STOPPED => ['Tisk zastaven', 'bad', false],
        FarmOrder::STATUS_CANCELLED => ['Zakázka zrušena', 'muted', false],
    ];

    /** The system's own notes most often seen (farm_order_events.note, 28 Nov 2026), in Czech; any other note as it is. */
    private const NOTES = [
        'guest order not paid within 7 days' => 'host nezaplatil do 7 dní',
        'no choices within 7 days' => 'zákazník 7 dní nevybral barvu a nastavení',
        'the slice waits for the word of the customer' => 'slicer čeká na volby zákazníka',
        'print_cancelled' => 'tisk zrušen na tiskárně',
    ];

    private const ACTORS = ['agent' => 'tiskárna', 'system' => 'systém', 'admin' => 'admin', 'user' => 'zákazník'];

    private const SOURCES = ['facebook' => 'Facebook', 'google' => 'Google', 'seznam' => 'Seznam', 'designer' => 'designér', 'direct' => 'přímo', 'other' => 'jinde'];

    /**
     * The stream, newest first, at most LIMIT lines.
     *
     * @param  string|null  $group  one of GROUPS, null = all
     * @param  int|null  $session  one visitor's whole way (anonymous_sessions.id): every step, the last 30 days
     * @return list<array{key: string, at: Carbon, group: string, label: string, tone: string, who: string, user: ?int, session: ?int, source: ?string, detail: list<string>, order: ?FarmOrder, intake: ?Intake}>
     */
    public function lines(int $hours, ?string $group = null, bool $detailed = false, ?int $session = null): array
    {
        $since = now()->subHours($session ? 24 * 30 : $hours);
        $detailed = $detailed || $session !== null;
        $lines = array_merge($this->events($since, $session), $this->orderEvents($since, $session));
        $lines = array_values(array_filter($lines, fn (array $l) => ($detailed || ! $l['quiet']) && ($group === null || $l['group'] === $group)));
        usort($lines, fn (array $a, array $b) => [$b['at']->getTimestamp(), $b['key']] <=> [$a['at']->getTimestamp(), $a['key']]);
        $lines = array_slice($lines, 0, self::LIMIT);

        return array_map(fn (array $l) => array_diff_key($l, ['quiet' => true]), $lines);
    }

    /** @return list<array<string, mixed>> */
    private function events(Carbon $since, ?int $session): array
    {
        $base = fn () => Event::query()->where('created_at', '>=', $since)->whereNull('meta->staff')->whereNull('meta->bot')
            ->when($session, fn ($q) => $q->where('session_id', $session));
        $acts = array_values(array_diff(array_merge(array_keys(self::EVENTS), [StepController::TYPE]), self::LOADS));
        $events = $base()->whereIn('type', $acts)->orderByDesc('id')->limit(self::LIMIT * 3)->get();

        // the visits of people in the window (a little before it: a session begins before its first act)
        $people = Event::query()->where('type', Event::VISIT)->where('created_at', '>=', $since->copy()->subDay())
            ->when($session, fn ($q) => $q->where('session_id', $session))
            ->whereNull('meta->staff')->whereNull('meta->bot')
            ->where(fn ($q) => $q->where('meta->js', true)->orWhere('meta->act', true))
            ->whereNotNull('session_id')->pluck('session_id')->unique()->values();
        if ($people->isNotEmpty()) {
            $loads = $people->chunk(1000)->flatMap(fn (Collection $ids) => $base()->whereIn('type', self::LOADS)->whereIn('session_id', $ids->all())->orderByDesc('id')->limit(self::LIMIT * 3)->get());
            $events = $events->concat($loads);
        }
        // a user's session that never confirmed is still a person: their page loads count too
        $events = $events->concat($base()->whereIn('type', self::LOADS)->whereNotNull('user_id')
            ->when($people->isNotEmpty(), fn ($q) => $q->whereNotIn('session_id', $people->all()))->orderByDesc('id')->limit(self::LIMIT)->get());
        $events = $events->unique('id');

        $users = User::whereIn('id', $events->pluck('user_id')->filter()->unique())->pluck('email', 'id');
        $files = ModelFile::whereIn('id', $events->where('subject_type', 'model_file')->pluck('subject_id')->unique())->get(['id', 'original_name', 'origin', 'origin_ref'])->keyBy('id');
        $intakes = Intake::whereIn('id', $events->where('subject_type', 'intake')->pluck('subject_id')->unique())->get()->keyBy('id');
        $orders = FarmOrder::whereIn('id', $events->map(fn (Event $e) => (int) ($e->meta['order'] ?? 0))->filter()->unique())->get(['id', 'number', 'token', 'status'])->keyBy('id');

        $out = [];
        foreach ($events as $e) {
            $meta = (array) $e->meta;
            $def = $e->type === StepController::TYPE ? (self::STEPS[(string) ($meta['step'] ?? '')] ?? null) : (self::EVENTS[$e->type] ?? null);
            if ($def === null) {
                continue;
            }
            [$group, $label, $tone, $quiet] = $def;
            $file = $e->subject_type === 'model_file' ? $files->get($e->subject_id) : null;
            $intake = $e->subject_type === 'intake' ? $intakes->get($e->subject_id) : null;
            $out[] = [
                'key' => 'e'.str_pad((string) $e->id, 12, '0', STR_PAD_LEFT),
                'at' => $e->created_at,
                'group' => $group,
                'label' => $label,
                'tone' => $tone,
                'quiet' => $quiet,
                'who' => $e->user_id ? (string) ($users[$e->user_id] ?? 'účet #'.$e->user_id) : ($e->session_id ? 'host #'.$e->session_id : 'host'),
                'user' => $e->user_id,
                'session' => $e->session_id,
                'source' => $this->source($e),
                'detail' => $this->detail($e->type, $meta, $file, $intake),
                'order' => isset($meta['order']) ? $orders->get((int) $meta['order']) : null,
                'intake' => $intake,
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function orderEvents(Carbon $since, ?int $session): array
    {
        $rows = FarmOrderEvent::query()->where('created_at', '>=', $since)
            ->when($session, fn ($q) => $q->whereHas('order', fn ($o) => $o->where('anonymous_session_id', $session)))
            ->with(['order' => fn ($q) => $q->select(['id', 'number', 'token', 'status', 'user_id', 'anonymous_session_id', 'model_file_id', 'farm_printer_id'])
                ->with(['user:id,email', 'modelFile:id,original_name', 'printer:id,name'])])
            ->orderByDesc('id')->limit(self::LIMIT * 2)->get();

        $out = [];
        foreach ($rows as $r) {
            $def = self::STATUSES[(string) $r->to] ?? null;
            if ($def === null || ! $r->order) {
                continue;
            }
            [$label, $tone, $quiet] = $def;
            $o = $r->order;
            $detail = array_filter([
                $o->modelFile?->original_name,
                in_array($r->to, [FarmOrder::STATUS_PRINTING, FarmOrder::STATUS_DONE, FarmOrder::STATUS_STOPPED, FarmOrder::STATUS_FAILED], true) ? $o->printer?->name : null,
                'udělal: '.(self::ACTORS[(string) $r->actor] ?? (string) $r->actor),
                $r->note ? mb_strimwidth(self::NOTES[(string) $r->note] ?? (string) $r->note, 0, 140, '…') : null,
            ]);
            $out[] = [
                'key' => 'o'.str_pad((string) $r->id, 12, '0', STR_PAD_LEFT),
                'at' => $r->created_at,
                'group' => 'print',
                'label' => $label,
                'tone' => $tone,
                'quiet' => $quiet,
                'who' => $o->user?->email ?? ($o->anonymous_session_id ? 'host #'.$o->anonymous_session_id : 'host'),
                'user' => $o->user_id,
                'session' => $o->anonymous_session_id,
                'source' => null,
                'detail' => array_values($detail),
                'order' => $o,
                'intake' => null,
            ];
        }

        return $out;
    }

    private function source(Event $e): ?string
    {
        $utm = (array) $e->utm;
        $name = self::SOURCES[(string) $e->source] ?? null;
        $ad = $utm['content'] ?? $utm['utm_content'] ?? $utm['campaign'] ?? $utm['utm_campaign'] ?? null;

        return $name === null || $e->source === 'direct' && ! $ad ? null : $name.($ad ? ' · '.$ad : '');
    }

    /**
     * The short words after the label: tool, file, kind, the reason of a failure, the money paid, the request's text.
     *
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    private function detail(string $type, array $meta, ?ModelFile $file, ?Intake $intake): array
    {
        // the order page's own steps say tool `order`: the order is the subject there, not a tool
        $tool = isset($meta['tool']) && $meta['tool'] !== 'order' ? $this->tool((string) $meta['tool']) : null;
        $kind = isset($meta['kind']) ? (string) $meta['kind'] : null;
        $out = [$tool, $file?->original_name];
        $out[] = match ($type) {
            'generate' => match ($kind) { 'image' => 'z fotky', 'text' => 'z textu', default => $kind },
            'upload' => $kind ? strtoupper($kind) : null,
            'price_shown' => match ($kind) { 'exact' => 'v kalkulačce', 'order' => 'na stránce objednávky', default => $kind },
            'checkout_started', 'order_paid' => match ($kind) { 'card' => 'kartou', 'credit' => 'z kreditu', default => $kind },
            'order_created' => match ($kind) { 'repeat' => 'opakovaná', 'tool' => 'rovnou z nástroje', default => null },
            'order_started' => match ($kind) { 'inspiration' => 'z inspirace', 'card' => 'z katalogu', 'calculation' => 'z kalkulačky', 'file' => 'ze souboru', default => $kind },
            'failed' => trim(($meta['step'] ?? '').' '.($meta['code'] ?? '')) ?: $kind,
            StepController::TYPE => isset($meta['ms']) ? 'po '.max(1, (int) round(((int) $meta['ms']) / 1000)).' s' : ($meta['code'] ?? null),
            'intake_chip', 'intake_spam' => $meta['code'] ?? null,
            default => null,
        };
        if ($type === 'order_paid' && isset($meta['value'])) {
            $out[] = number_format((float) $meta['value'], 0, ',', ' ').' '.($meta['currency'] ?? 'Kč');
        }
        if ($intake && in_array($type, ['intake_submitted', 'intake_human_created', 'intake_designed', 'intake_routed', 'intake_rerouted'], true)) {
            $text = trim((string) ($intake->text ?: $intake->summary));
            $out[] = $text !== '' ? '„'.mb_strimwidth($text, 0, 160, '…').'“' : null;
            $out[] = $type !== 'intake_submitted' && $intake->tool_key ? $this->tool((string) $intake->tool_key) : null;
        }

        return array_values(array_unique(array_filter($out, fn ($v) => $v !== null && $v !== '')));
    }

    /** The tool's page name ("Busta nebo figurka z fotky"), its key when it has none. */
    private function tool(string $key): string
    {
        $name = 'tools.'.$key.'.title';

        return __($name, [], 'cs') !== $name ? (string) __($name, [], 'cs') : $key;
    }
}
