<?php

namespace App\Http\Controllers;

use App\Engines\Converter\ConverterChain;
use App\Support\Track;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The calculator other sites can embed (docs/AN.md §12, traffic): /embed is the page with the iframe code for blogs
 * and shops selling filament, /embed/calc the small page inside the frame (upload → the price → "open on matplace.com"
 * with utm_source=embed). The frame needs no cookie: the file and the calculation are made for whoever holds the link,
 * the link opens matplace.com in a new tab. Where the frame was shown is recorded by the host of the page around it.
 */
class EmbedController extends Controller
{
    public function index(ConverterChain $converters): View
    {
        return view('embed.index', ['code' => self::code(), 'formats' => $converters->inputFormats()]);
    }

    public function calc(Request $request, ConverterChain $converters): View
    {
        $host = strtolower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));
        if ($host !== '' && $host !== strtolower($request->getHost())) {
            Track::event('embed', null, ['host' => mb_substr($host, 0, 80)]);
        }

        return view('embed.calc', [
            'host' => $host,
            'formats' => $converters->inputFormats(),
            'maxMb' => (int) config('uploads.max_mb', 120),
        ]);
    }

    /** The iframe a site pastes: the calculator in the language of the page it was copied from. */
    public static function code(): string
    {
        return '<iframe src="'.route('embed.calc').'" width="100%" height="460" style="border:0;border-radius:12px" loading="lazy" title="'.e(__('embed.frame_title')).'"></iframe>';
    }
}
