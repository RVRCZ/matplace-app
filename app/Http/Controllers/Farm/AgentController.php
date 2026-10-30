<?php

namespace App\Http\Controllers\Farm;

use App\Domain\Farm\AgentService;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\GcodeSlot;
use App\Domain\Farm\PrinterFiles;
use App\Domain\Farm\PrintProfile;
use App\Domain\Farm\TimelapseGcode;
use App\Http\Controllers\Controller;
use App\Models\FarmAgent;
use App\Models\FarmCommand;
use App\Models\FarmPrinter;
use App\Models\FarmPrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * API for farm-agent. The agent calls us (outgoing HTTPS from the farm's network), never the other way round.
 * Stateless: no session, no cookies; every call carries the agent's bearer token.
 */
class AgentController extends Controller
{
    public function __construct(private readonly AgentService $service) {}

    /** POST /api/agent/sync — heartbeat + printer and job reports in, pending commands out. Called every few seconds. */
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'version' => ['nullable', 'string', 'max:40'],
            'printers' => ['present', 'array', 'max:64'],
            'printers.*.key' => ['required', 'string', 'max:40'],
            'printers.*.state' => ['required', 'string', 'max:12'],
            'printers.*.telemetry' => ['nullable', 'array'],
            'printers.*.job' => ['nullable', 'array'],
            'printers.*.job.id' => ['required_with:printers.*.job', 'integer'],
            'printers.*.job.status' => ['required_with:printers.*.job', 'string', 'max:12'],
        ]);
        $agent = $this->agent($request);
        // validated() drops the free-form telemetry keys; the reports are read from the raw input, the service sanitises them
        $commands = $this->service->sync($agent, (array) $request->input('printers', []), $data['version'] ?? null, $request->ip());

        return response()->json([
            'commands' => $commands,
            'printers' => $agent->printers()->where('enabled', true)->pluck('key'),
            'poll_seconds' => 5,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** POST /api/agent/commands/{command}/result */
    public function commandResult(Request $request, FarmCommand $command): JsonResponse
    {
        $data = $request->validate(['ok' => ['required', 'boolean'], 'message' => ['nullable', 'string', 'max:300']]);
        $this->service->commandResult($this->agent($request), $command, (bool) $data['ok'], $data['message'] ?? null);

        return response()->json(['ok' => true]);
    }

    /** GET /api/agent/jobs/{job}/gcode — the G-code for the job's slot; the hash lets the agent verify the download. */
    public function gcode(Request $request, FarmPrintJob $job): BinaryFileResponse
    {
        abort_unless($job->printer->farm_agent_id === $this->agent($request)->id && $job->isActive(), 404);
        $source = $job->order->absoluteGcodePath($job->plate);
        abort_unless($source && is_file($source), 404);
        // a plate of several filaments maps every T<n> to its slot (and says so in the header); any other plate selects its one slot
        $path = $job->order->onePlate()
            ? GcodeSlot::fileForMany($source, $job->order->filamentSlots(), PrintProfile::tempsForFilaments($job->order))
            : GcodeSlot::fileFor($source, $job->slot, PrintProfile::tempsForPlate($job->order, $job->plate), $job->order->isByParts() ? [] : $job->order->colorChanges());
        // the head steps out of the camera's way after every layer (only machines and orders set up for it)
        if ($timelapse = $job->printer->timelapseFor($job->order)) {
            $path = TimelapseGcode::fileFor($path, $timelapse);
        }

        return response()->download($path, $job->remote_filename ?: 'print.gcode', [
            'Content-Type' => 'text/x.gcode', 'X-Content-Sha256' => hash_file('sha256', $path),
        ]);
    }

    /** POST /api/agent/printers/{key}/snapshot — multipart "image" (JPEG). Kept on the printer and on its running job. */
    public function snapshot(Request $request, string $key, FarmSettings $settings): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg', 'max:4096'], 'job_id' => ['nullable', 'integer'], 'frame' => ['nullable', 'in:layer']]);
        $printer = FarmPrinter::where('key', $key)->where('farm_agent_id', $this->agent($request)->id)->firstOrFail();
        $disk = Storage::disk(config('farm.disk'));

        $rel = 'printers/'.$printer->id.'/snapshot.jpg';
        $disk->putFileAs(dirname($rel), $request->file('image'), basename($rel));
        $printer->update(['snapshot_path' => $rel, 'snapshot_at' => now()]);

        $job = $request->filled('job_id') ? FarmPrintJob::where('id', $request->integer('job_id'))->where('farm_printer_id', $printer->id)->first() : null;
        if ($job) {
            // the last picture is what people see live; one frame a minute is kept for the time-lapse of the finished print
            $jobRel = $job->order->dir().'/snapshot.jpg';
            $disk->put($jobRel, $disk->get($rel));
            $frames = $job->order->dir().'/frames';
            if ($request->input('frame') === 'layer') {
                // the head stands in its park position after a layer: every one of these is a frame (TimelapseGcode)
                $disk->put($job->order->dir().'/frames_layer/'.sprintf('%.3f', microtime(true)).'.jpg', $disk->get($rel));
            } else {
                $last = collect($disk->files($frames))->max();
                if (! $last || now()->timestamp - (int) basename($last, '.jpg') >= 55) {
                    $disk->put($frames.'/'.now()->timestamp.'.jpg', $disk->get($rel));
                }
            }
            $job->update(['snapshot_path' => $jobRel, 'snapshot_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    /** POST /api/agent/printers/{key}/files — the printer's file list (names, sizes, dates, free space; after a delete what went). */
    public function files(Request $request, string $key): JsonResponse
    {
        $request->validate([
            'files' => ['present', 'array', 'max:3000'], 'files.*.name' => ['required', 'string', 'max:200'],
            'files.*.size' => ['nullable', 'integer', 'min:0'], 'files.*.modified' => ['nullable', 'integer', 'min:0'],
            'free' => ['nullable', 'integer', 'min:0'], 'total' => ['nullable', 'integer', 'min:0'],
            'deleted' => ['nullable', 'array', 'max:500'], 'deleted.*.name' => ['required', 'string', 'max:200'], 'deleted.*.size' => ['nullable', 'integer', 'min:0'],
            'freed' => ['nullable', 'integer', 'min:0'],
        ]);
        $printer = FarmPrinter::where('key', $key)->where('farm_agent_id', $this->agent($request)->id)->firstOrFail();
        $this->service->filesReport($printer, $request->all());

        return response()->json(['ok' => true]);
    }

    /** POST /api/agent/printers/{key}/files/upload — one file of the printer, multipart "file" + "name", 300 MB at most. */
    public function uploadFile(Request $request, string $key, PrinterFiles $files): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:'.(PrinterFiles::MAX_BYTES >> 10)], 'name' => ['required', 'string', 'max:200']]);
        $printer = FarmPrinter::where('key', $key)->where('farm_agent_id', $this->agent($request)->id)->firstOrFail();
        $name = PrinterFiles::plain($request->input('name'));
        abort_if($name === null, 422, 'not a plain file name');
        $size = $files->save($printer, $request->file('file'), $name);

        return response()->json(['ok' => true, 'name' => $name, 'size' => $size]);
    }

    private function agent(Request $request): FarmAgent
    {
        return $request->attributes->get('farm_agent');
    }
}
