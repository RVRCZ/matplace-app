<?php

namespace App\Http\Controllers\Farm;

use App\Domain\Farm\AgentService;
use App\Domain\Farm\FarmSettings;
use App\Domain\Farm\GcodeSlot;
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
        $source = $job->order->absoluteGcodePath();
        abort_unless($source && is_file($source), 404);
        $path = GcodeSlot::fileFor($source, $job->slot);

        return response()->download($path, $job->remote_filename ?: 'print.gcode', [
            'Content-Type' => 'text/x.gcode', 'X-Content-Sha256' => hash_file('sha256', $path),
        ]);
    }

    /** POST /api/agent/printers/{key}/snapshot — multipart "image" (JPEG). Kept on the printer and on its running job. */
    public function snapshot(Request $request, string $key, FarmSettings $settings): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'mimes:jpg,jpeg', 'max:4096'], 'job_id' => ['nullable', 'integer']]);
        $printer = FarmPrinter::where('key', $key)->where('farm_agent_id', $this->agent($request)->id)->firstOrFail();
        $disk = Storage::disk(config('farm.disk'));

        $rel = 'printers/'.$printer->id.'/snapshot.jpg';
        $disk->putFileAs(dirname($rel), $request->file('image'), basename($rel));
        $printer->update(['snapshot_path' => $rel, 'snapshot_at' => now()]);

        $job = $request->filled('job_id') ? FarmPrintJob::where('id', $request->integer('job_id'))->where('farm_printer_id', $printer->id)->first() : null;
        if ($job) {
            // one picture per order: the customer and the admin see the last one, nothing piles up
            $jobRel = $job->order->dir().'/snapshot.jpg';
            $disk->put($jobRel, $disk->get($rel));
            $job->update(['snapshot_path' => $jobRel, 'snapshot_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    private function agent(Request $request): FarmAgent
    {
        return $request->attributes->get('farm_agent');
    }
}
