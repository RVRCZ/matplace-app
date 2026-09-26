<?php

namespace App\Jobs;

use App\Domain\Tools\PrintAdvisor;
use App\Models\GenerationRequest;
use App\Models\ModelFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/** Print advice for one model (PrintAdvisor); the calculator polls the request until it is done. */
class GiveModelAdvice implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public readonly int $requestId) {}

    public function handle(PrintAdvisor $advisor): void
    {
        $req = GenerationRequest::find($this->requestId);
        $file = $req ? ModelFile::find($req->result_model_file_id) : null;
        if (! $req || ! $file || $req->status !== 'running') {
            return;
        }
        $meta = (array) $req->description;
        try {
            $advice = $advisor->advise($file, (string) ($meta['locale'] ?? 'cs'), (array) ($meta['context'] ?? []));
            $req->update(['status' => 'done', 'description' => $meta + ['advice' => $advice]]);
        } catch (\Throwable $e) {
            Log::warning('advice for '.$file->uuid.' failed: '.$e->getMessage());
            $req->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }
    }
}
