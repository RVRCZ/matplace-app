<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Farm\FarmSettings;
use App\Domain\Mail\MailboxWatch;
use App\Domain\Ops\HealthChecks;
use App\Http\Controllers\Controller;
use App\Models\HealthCheck;
use App\Models\MailboxAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** The watch in the admin (docs/AU.md): every server check with its state, the last pass over the inbox, who was told about what. */
class HealthController extends Controller
{
    public function index(FarmSettings $settings): View
    {
        return view('admin.health', [
            'checks' => HealthCheck::orderBy('key')->get(), 'labels' => HealthChecks::LABELS,
            'mailbox' => Cache::get(MailboxWatch::CACHE), 'alerts' => MailboxAlert::latest('id')->limit(30)->get(),
            'to' => (string) $settings->get('alert_email'),
        ]);
    }

    /** "Check now": both commands at once, their output shown under the table. */
    public function run(): RedirectResponse
    {
        $out = [];
        foreach (['matplace:health', 'matplace:watch-inbox'] as $command) {
            try {
                Artisan::call($command);
                $out[] = trim(Artisan::output());
            } catch (\Throwable $e) {
                $out[] = $command.': '.$e->getMessage();
            }
        }

        return redirect()->route('admin.health')->with('status', 'Kontrola proběhla.')->with('health_output', implode("\n", $out));
    }
}
