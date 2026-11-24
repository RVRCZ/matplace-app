<?php

namespace App\Console\Commands;

use App\Domain\Tools\ModelPainter;
use App\Mail\ModelLink;
use App\Models\FarmOrder;
use App\Models\ModelLead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * The one reminder a visitor ticked at "send me my bust" (App\Models\ModelLead): REMIND_AFTER_DAYS after the link, once,
 * unless he unsubscribed, the model is gone or an order of it (or of its painted twin) was paid meanwhile.
 */
class SendLeadReminders extends Command
{
    protected $signature = 'matplace:lead-reminders {--dry-run}';

    protected $description = 'Send the one reminder visitors asked for at their model from a photo';

    public function handle(): int
    {
        $sent = 0;
        ModelLead::with('modelFile')->where('remind', true)->whereNull('reminded_at')->whereNull('unsubscribed_at')->whereNotNull('consent_at')
            ->where('sent_at', '<=', now()->subDays(ModelLead::REMIND_AFTER_DAYS))
            ->where('sent_at', '>=', now()->subDays(ModelLead::REMIND_AFTER_DAYS + 5))   // a lead long past its day is not woken up
            ->each(function (ModelLead $lead) use (&$sent) {
                $file = $lead->modelFile;
                $ids = array_filter([$file?->id, $file ? ModelPainter::twinOf($file)?->id : null]);
                $paid = $ids && FarmOrder::whereIn('model_file_id', $ids)->whereNotNull('paid_at')->exists();
                if (! $file || $paid) {
                    $this->option('dry-run') || $lead->forceFill(['reminded_at' => now()])->save();   // nothing to remind of: never again

                    return;
                }
                $sent++;
                if ($this->option('dry-run')) {
                    return;
                }
                $lead->forceFill(['reminded_at' => now()])->save();
                Mail::to($lead->email)->locale($lead->locale)->queue((new ModelLink($lead, true))->locale($lead->locale));
            });
        $this->line("reminders: {$sent}");

        return self::SUCCESS;
    }
}
