<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Stats\AiActivity;
use App\Domain\Stats\Funnel;
use App\Domain\Stats\Speed;
use App\Http\Controllers\Controller;
use App\Models\SearchQuery;
use App\Support\Locales;
use App\Support\ToolSeo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * /admin/stats: what visitors do (three funnels from our own events, by source, language and tool, and what each
 * designer's links brought), what the AI costs, and what people search for.
 */
class StatsController extends Controller
{
    public function funnel(Request $request, Funnel $funnel): View
    {
        $days = (int) $request->query('days', 30);
        $source = in_array($request->query('source'), Funnel::SOURCES, true) ? (string) $request->query('source') : null;
        $locale = in_array($request->query('locale'), Locales::SUPPORTED, true) ? (string) $request->query('locale') : null;
        $tool = in_array($request->query('tool'), ToolSeo::tools(), true) ? (string) $request->query('tool') : null;

        return view('admin.stats.funnel', ['stats' => $funnel->compute($days, $source, $locale, $tool), 'source' => $source, 'locale' => $locale, 'tool' => $tool, 'tools' => ToolSeo::tools()]);
    }

    public function ai(Request $request, AiActivity $activity): View
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;

        return view('admin.ai.index', ['stats' => $activity->summary($days)]);
    }

    /** How long customers wait for the server: queue, repair, slicing… per step (matplace:perf-report on a page). */
    public function speed(Request $request, Speed $speed): View
    {
        $days = in_array((int) $request->query('days'), [1, 7, 30], true) ? (int) $request->query('days') : 7;

        return view('admin.stats.speed', ['stats' => $speed->summary($days)]);
    }

    /** What people search for: the most frequent queries, the ones our own catalogue had nothing for, by language. */
    public function search(Request $request): View|StreamedResponse
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90], true) ? (int) $request->query('days') : 30;
        $locale = in_array($request->query('locale'), Locales::SUPPORTED, true) ? (string) $request->query('locale') : null;
        $base = fn () => SearchQuery::where('created_at', '>=', now()->subDays($days))->when($locale, fn ($q) => $q->where('locale', $locale));
        $grouped = fn ($query) => $query->select('query', DB::raw('COUNT(*) as searches'), DB::raw('COUNT(DISTINCT visitor) as people'), DB::raw('MAX(results_local) as local'), DB::raw('MAX(results_external) as external'), DB::raw('MAX(created_at) as last_at'))
            ->groupBy('query')->orderByDesc('searches')->orderBy('query');

        if ($request->query('export') === 'csv') {
            $rows = $grouped($base())->limit(5000)->get();

            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");   // so a spreadsheet reads the accents
                fputcsv($out, ['dotaz', 'hledání', 'lidí', 'výsledků v našem katalogu', 'výsledků jinde', 'naposledy'], ';');
                foreach ($rows as $r) {
                    fputcsv($out, [$r->query, $r->searches, $r->people, $r->local, $r->external, $r->last_at], ';');
                }
                fclose($out);
            }, 'matplace-hledani-'.$days.'-dni.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
        }

        return view('admin.stats.search', [
            'days' => $days, 'locale' => $locale, 'total' => $base()->count(),
            'top' => $grouped($base())->limit(50)->get(),
            'empty' => $grouped($base()->where('results_local', 0))->limit(50)->get(),
            'byLocale' => $base()->select('locale', DB::raw('COUNT(*) as searches'), DB::raw('SUM(CASE WHEN results_local = 0 THEN 1 ELSE 0 END) as without'))->groupBy('locale')->orderByDesc('searches')->get(),
        ]);
    }
}
