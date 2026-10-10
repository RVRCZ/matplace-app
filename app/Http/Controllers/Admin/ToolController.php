<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Tools\ToolVisibility;
use App\Http\Controllers\Controller;
use App\Models\ToolFlag;
use App\Support\Sitemaps;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * /admin/tools: which tools of config/tools.php the public sees. A tool switched off here leaves the catalogue, the
 * sitemap and the links, and its page answers 404 to everybody but an admin, who goes on trying it
 * (App\Domain\Tools\ToolVisibility, App\Http\Middleware\ToolGate).
 */
class ToolController extends Controller
{
    public const FILTERS = ['all', 'hidden', 'unverified'];

    public function index(Request $request): View
    {
        $show = in_array($request->query('show'), self::FILTERS, true) ? (string) $request->query('show') : 'all';
        $flags = ToolFlag::with('editor')->get()->keyBy('tool');
        $rows = [];
        $counts = array_fill_keys(self::FILTERS, 0);
        foreach ((array) config('tools') as $key => $tool) {
            $route = Route::getRoutes()->getByName((string) $tool['route']);
            $row = [
                'key' => (string) $key,
                'title' => Lang::has('tools.'.$key.'.title') ? __('tools.'.$key.'.title') : (string) $key,
                'url' => $route ? route($tool['route']) : null,
                'path' => $route ? '/'.ltrim($route->uri(), '/') : null,
                'categories' => (array) $tool['categories'],
                'verified' => $tool['verified'] ?? null,
                'config' => ! empty($tool['available']),
                'listed' => ToolVisibility::isPublic((string) $key),
                'flag' => $flags->get($key),
            ];
            $in = ['all' => true, 'hidden' => ! $row['listed'], 'unverified' => $row['verified'] === null];
            foreach ($in as $filter => $matches) {
                $counts[$filter] += (int) $matches;
            }
            if ($in[$show]) {
                $rows[] = $row;
            }
        }

        return view('admin.tools.index', ['rows' => $rows, 'show' => $show, 'counts' => $counts]);
    }

    public function update(Request $request, string $tool, Sitemaps $sitemaps): JsonResponse|RedirectResponse
    {
        abort_unless(isset(config('tools')[$tool]), 404);
        $data = $request->validate(['public' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:1000']]);

        $flag = ToolFlag::updateOrCreate(['tool' => $tool], [
            'public' => (bool) $data['public'],
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
            'updated_by' => $request->user()->id,
        ]);
        // the sitemap is written once a night: the tools' part of it follows the switch now
        $sitemaps->refreshTools();

        if (! $request->expectsJson()) {
            return back()->with('status', __('tools.admin.saved'));
        }

        return response()->json([
            'tool' => $tool,
            'public' => $flag->public,
            'listed' => ToolVisibility::isPublic($tool),
            'changed' => self::changed($flag),
        ]);
    }

    /** "Roman · 10. 10. 2026 14:05": who touched the switch last. */
    public static function changed(?ToolFlag $flag): string
    {
        return $flag ? implode(' · ', array_filter([$flag->editor?->name, $flag->updated_at->timezone('Europe/Prague')->format('j. n. Y H:i')])) : '—';
    }
}
