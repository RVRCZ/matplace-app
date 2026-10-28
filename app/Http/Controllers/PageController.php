<?php

namespace App\Http\Controllers;

use App\Domain\Farm\OrderService;
use App\Support\LandingPage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Lang;

/**
 * Static pages: about us, contact, questions and answers, complaints, terms of use, business terms, cookies.
 * The texts are lang/<locale>/pages.php (the admin does not edit them); the address says which page.
 */
class PageController extends Controller
{
    /** page key → the last part of its address */
    public const PAGES = [
        'about' => 'about', 'contact' => 'contact', 'faq' => 'faq', 'complaints' => 'complaints',
        'terms' => 'terms', 'business_terms' => 'business-terms', 'cookies' => 'cookies',
        'business' => 'business',   // 3D printing for businesses: the business intent of the tools as a page (docs/AN.md §8)
        'praha' => '3d-tisk-praha',   // the local entry page: the farm prints in Prague (docs/AP.md §8)
    ];

    /** The name of a page's route: `pages.<page>`, except the page for businesses, which the home page and the tools know as `tools.business`. */
    public static function routeName(string $page): string
    {
        return $page === 'business' ? 'tools.business' : 'pages.'.$page;
    }

    /** What we print from: the materials of the price calculator with what each stands up to, and what is loaded right now. */
    public function materials(OrderService $orders): View
    {
        $materials = array_filter((array) config('materials.items'), fn (array $m) => isset($m['props']) && ($m['slice'] ?? true) !== false);
        uasort($materials, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        return view('pages.materials', [
            'materials' => $materials,
            'loaded' => config('farm.enabled') ? $orders->loadedMaterials()->map(fn ($m) => $m->label())->unique()->values() : collect(),
        ]);
    }

    public function __invoke(string $page): View
    {
        abort_unless(isset(self::PAGES[$page]) && Lang::has('pages.'.$page.'.title'), 404);

        // today's delivery prices in the visitor's currency where a text names them (:cz_point …, App\Support\LandingPage)
        $numbers = LandingPage::numbers();
        $fill = function ($value) use (&$fill, $numbers) {
            return is_array($value) ? array_map($fill, $value) : (is_string($value) ? strtr($value, $numbers) : $value);
        };

        return view('pages.static', ['key' => $page, 'page' => $fill((array) __('pages.'.$page)), 'updated' => Lang::has('pages.updated') ? __('pages.updated') : null]);
    }
}
