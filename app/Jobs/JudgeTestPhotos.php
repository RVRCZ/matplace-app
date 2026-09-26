<?php

namespace App\Jobs;

use App\Domain\Farm\TestPhotoJudge;
use App\Models\FarmOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * The photos of a test print go to the judge; its reading lands in test_params.ai, where the tuning page prefills
 * the evaluation form from it. Nothing is evaluated until the operator submits that form.
 */
class JudgeTestPhotos implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly int $orderId) {}

    public function handle(TestPhotoJudge $judge): void
    {
        $order = FarmOrder::find($this->orderId);
        if (! $order || ! $order->isTest()) {
            return;
        }
        $photos = count((array) ($order->test_params['photos'] ?? []));
        try {
            $ai = ['status' => 'done', 'at' => now()->toIso8601String(), 'photos' => $photos] + $judge->judge($order);
        } catch (\Throwable $e) {
            Log::warning('farm: judging photos of '.$order->number.' failed: '.$e->getMessage());
            $ai = ['status' => 'failed', 'at' => now()->toIso8601String(), 'error' => mb_substr($e->getMessage(), 0, 300)];
        }
        $order->refresh();
        $order->forceFill(['test_params' => ['ai' => $ai] + (array) $order->test_params])->save();
    }
}
