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
 * (App\Domain\Tools\ToolVisibility, App\Http\Middleware\ToolGate). A second switch puts a tool on the home page.
 */
class ToolController extends Controller
{
    public const FILTERS = ['all', 'hidden', 'unverified', 'home'];

    public function index(Request $request): View
    {
        $show = in_array($request->query('show'), self::FILTERS, true) ? (string) $request->query('show') : 'all';
        $flags = ToolFlag::with('editor')->get()->keyBy('tool');
        $rows = [];
        $counts = array_fill_keys(self::FILTERS, 0);
        $home = ToolVisibility::onHome();
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
                'home' => ToolVisibility::wantsHome((string) $key),
                'home_hint' => self::homeHint((string) $key),
                'flag' => $flags->get($key),
            ];
            $in = ['all' => true, 'hidden' => ! $row['listed'], 'unverified' => $row['verified'] === null, 'home' => in_array($key, $home, true)];
            foreach ($in as $filter => $matches) {
                $counts[$filter] += (int) $matches;
            }
            if ($in[$show]) {
                $rows[] = $row;
            }
        }

        // the cards as the home page lays them out
        if ($show === 'home') {
            usort($rows, fn (array $a, array $b) => array_search($a['key'], $home, true) <=> array_search($b['key'], $home, true));
        }

        return view('admin.tools.index', ['rows' => $rows, 'show' => $show, 'counts' => $counts, 'homeNote' => self::homeNote()]);
    }

    public function update(Request $request, string $tool, Sitemaps $sitemaps): JsonResponse|RedirectResponse
    {
        abort_unless(isset(config('tools')[$tool]), 404);
        $data = $request->validate(['public' => ['required', 'boolean'], 'home' => ['sometimes', 'nullable', 'boolean'], 'note' => ['nullable', 'string', 'max:1000']]);
        // every save of a row sends its home box too: a box left as it stood decides nothing and the config keeps deciding
        $home = ToolFlag::query()->where('tool', $tool)->value('home');
        if (isset($data['home']) && (bool) $data['home'] !== ToolVisibility::wantsHome($tool)) {
            $home = (bool) $data['home'];
        }

        $flag = ToolFlag::updateOrCreate(['tool' => $tool], [
            'public' => (bool) $data['public'],
            'home' => $home,
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
            'home' => $flag->home,
            'home_hint' => self::homeHint($tool),
            'home_note' => self::homeNote(),
            'changed' => self::changed($flag),
        ]);
    }

    /** How many tools are switched on for the home page, and what its limit leaves out. */
    public static function homeNote(): string
    {
        $on = count(ToolVisibility::onHome());
        $shown = (int) config('home.tools_shown', 8);

        return __('tools.admin.home.count', ['n' => $on, 'max' => $shown]).($on > $shown ? ' '.__('tools.admin.home.over', ['max' => $shown]) : '');
    }

    /** Why a tool's switch does not put it on the page: it is not public, or it is further down than the page's limit. */
    private static function homeHint(string $tool): string
    {
        if (! ToolVisibility::isPublic($tool)) {
            return __('tools.admin.home.hint');
        }
        $at = array_search($tool, ToolVisibility::onHome(), true);
        $shown = (int) config('home.tools_shown', 8);

        return $at !== false && $at >= $shown ? __('tools.admin.home.beyond', ['at' => $at + 1, 'max' => $shown]) : '';
    }

    /** "Roman · 10. 10. 2026 14:05": who touched the switch last. */
    public static function changed(?ToolFlag $flag): string
    {
        return $flag ? implode(' · ', array_filter([$flag->editor?->name, $flag->updated_at->timezone('Europe/Prague')->format('j. n. Y H:i')])) : '—';
    }
}
