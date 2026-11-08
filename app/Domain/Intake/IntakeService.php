<?php

namespace App\Domain\Intake;

use App\Domain\Farm\OrderFlow;
use App\Engines\Converter\ConverterChain;
use App\Engines\Repair\PythonTool;
use App\Http\Controllers\Api\UploadController;
use App\Mail\IntakeReceived;
use App\Models\Intake;
use App\Models\IntakeRequest;
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

    public function __construct(private readonly IntakeRouter $router, private readonly PythonTool $python, private readonly ConverterChain $converters) {}

    /**
     * POST /intake: a photo, a 3D file or words (at least one), stored and decided.
     *
     * @throws ValidationException
     */
    public function create(Request $request): Intake
    {
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
        Track::event('intake_submitted', $intake, ['kind' => $intake->kind, 'has_text' => $text !== '']);

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
     * What the page gets back: the route, the sentence, the parameters and where each way on leads.
     *
     * @return array<string, mixed>
     */
    public function describe(Intake $intake): array
    {
        $tool = $intake->route === Intake::ROUTE_TOOL && $intake->tool_key ? $intake->tool_key : null;
        $file = $intake->route === Intake::ROUTE_FILE ? $intake->modelFile : null;
        $calc = $file ? route('home').'?'.http_build_query(array_filter(['open' => $file->uuid, 'note' => $intake->text])) : null;

        return [
            'token' => $intake->token,
            'route' => $intake->route,
            'tool_key' => $tool,
            'tool' => $tool ? __('tools.'.$tool.'.title') : null,
            'summary' => (string) $intake->summary,
            'params' => (array) $intake->params,
            'questions' => array_values((array) ($intake->answer['questions'] ?? [])),
            'wants_shape' => (bool) $intake->wants_shape,
            'has_photo' => $intake->hasPhoto(),
            'hours' => IntakePage::hours(),
            'file' => $file ? ['uuid' => $file->uuid, 'name' => $file->original_name] : null,
            'actions' => [
                'tool' => $tool ? ToolSeo::url($tool).'?intake='.$intake->token : null,
                'calc' => $calc,
                'request' => route('intake.request', $intake),
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

        return [
            'token' => $intake->token,
            'tool' => $tool,
            'file' => $intake->hasPhoto() ? route('intake.file', $intake) : null,
            'name' => $intake->photo_path ? basename($intake->photo_path) : null,
            'params' => (array) $intake->params,
            'text' => $intake->text,
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
