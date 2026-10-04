<?php

namespace App\Http\Middleware;

use App\Models\MissingPage;
use App\Support\Bots;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Collects the addresses that ended in "not found" (missing_pages): after the move from the old site they are the
 * list of redirects still to decide (config/legacy.php). One row per address with how often people and robots
 * asked for it and who linked there; nothing about the visitor is kept.
 */
class RecordMissing
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response->getStatusCode() === 404 && ($request->isMethod('GET') || $request->isMethod('HEAD'))) {
            try {
                $this->record($request);
            } catch (\Throwable $e) {
                Log::warning('Missing page was not recorded', ['error' => $e->getMessage()]);
            }
        }

        return $response;
    }

    private function record(Request $request): void
    {
        $path = '/'.ltrim($request->path(), '/');
        if (! MissingPage::worthKeeping($path)) {
            return;
        }
        $bot = Bots::is($request) ? 1 : 0;
        $now = now();
        $changed = MissingPage::where('path', $path)->update(['hits' => DB::raw('hits + 1'), 'bot_hits' => DB::raw('bot_hits + '.$bot), 'last_at' => $now]);
        if ($changed === 0) {
            MissingPage::insertOrIgnore(['path' => $path, 'hits' => 1, 'bot_hits' => $bot, 'first_at' => $now, 'live_at' => $now, 'last_at' => $now]);
        } else {
            MissingPage::where('path', $path)->whereNull('live_at')->update(['live_at' => $now]);   // known from the log only until now
        }
        $referer = (string) $request->headers->get('referer');
        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        if ($host !== '' && $host !== strtolower($request->getHost())) {
            MissingPage::where('path', $path)->update(['referer' => mb_substr($host.parse_url($referer, PHP_URL_PATH), 0, 190)]);
        }
    }
}
