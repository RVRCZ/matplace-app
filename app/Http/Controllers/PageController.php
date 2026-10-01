<?php

namespace App\Http\Controllers;

use App\Domain\Farm\OrderService;
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
    ];

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

        return view('pages.static', ['key' => $page, 'page' => (array) __('pages.'.$page), 'updated' => Lang::has('pages.updated') ? __('pages.updated') : null]);
    }
}
