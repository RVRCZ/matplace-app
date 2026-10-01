<?php

namespace App\Http\Controllers;

use App\Support\Sitemaps;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Serves the sitemap files written by `matplace:sitemap`, and robots.txt that points to them. */
class SitemapController extends Controller
{
    public function index(Sitemaps $sitemaps): BinaryFileResponse
    {
        // never built on this machine (a fresh install): build once, the scheduler keeps it fresh from then on
        if (! is_file(Sitemaps::dir().'/sitemap.xml')) {
            $sitemaps->build();
        }

        return $this->file('sitemap.xml');
    }

    public function show(string $name): BinaryFileResponse
    {
        return $this->file('sitemap-'.$name.'.xml');
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *'];
        if (! config('seo.indexable', true)) {
            // a staging site: nothing is to be kept by search engines
            $lines[] = 'Disallow: /';
        } else {
            foreach ((array) config('seo.robots_disallow') as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = '';
            $lines[] = 'Sitemap: '.url('/sitemap.xml');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    private function file(string $name): BinaryFileResponse
    {
        $path = Sitemaps::dir().'/'.$name;
        abort_unless(preg_match('/^sitemap(-[a-z0-9-]+)?\.xml$/', $name) && is_file($path), 404);

        return response()->file($path, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600', 'X-Robots-Tag' => 'noindex']);
    }
}
