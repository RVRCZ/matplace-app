<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\ModelPricing;
use App\Models\DesignerModel;
use App\Models\DesignerProfile;
use App\Models\Post;
use App\Support\Locales;
use App\Support\Money;
use App\Support\OgImage;
use App\Support\ToolSeo;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * /og/{type}/{id}.png: pictures for link previews (1200 × 630), drawn on demand with GD and kept on the disk under
 * the hash of what went in. /og/{locale}/{type}/{id}.png is the same picture with its words in English or Spanish.
 *
 * Types: designer (a portfolio), model (a model the farm prints), tool, article, site.
 */
class OgController extends Controller
{
    public const TYPES = ['designer', 'model', 'tool', 'article', 'site'];

    public function show(string $type, string $id): BinaryFileResponse
    {
        return $this->draw($type, $id, Locales::DEFAULT);
    }

    public function showIn(string $locale, string $type, string $id): BinaryFileResponse
    {
        return $this->draw($type, $id, $locale);
    }

    /** Address of the picture of a page, in the language being rendered. */
    public static function url(string $type, string $id): string
    {
        $locale = app()->getLocale();

        return $locale === Locales::DEFAULT ? route('og', ['type' => $type, 'id' => $id]) : route('og.localized', ['locale' => $locale, 'type' => $type, 'id' => $id]);
    }

    private function draw(string $type, string $id, string $locale): BinaryFileResponse
    {
        // the words of the picture are in the language of the address, whatever the visitor's own language is
        app()->setLocale($locale);
        $path = match ($type) {
            'designer' => $this->designer($id, $locale),
            'model' => $this->model($id, $locale),
            'tool' => $this->tool($id, $locale),
            'article' => $this->article($id, $locale),
            'site' => OgImage::site(__('site.og.title', [], $locale), __('site.og.line', [], $locale)),
            default => abort(404),
        };

        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400', 'X-Robots-Tag' => 'noindex']);
    }

    private function designer(string $slug, string $locale): string
    {
        $designer = DesignerProfile::where('slug', $slug)->where('visible', true)->firstOrFail();
        $covers = [];
        foreach ($designer->models()->with('images')->where('visible', true)->latest('id')->limit(12)->get() as $card) {
            if (($cover = $card->cover()) && count($covers) < 3) {
                $covers[] = Storage::disk('public')->path($cover->path);
            }
        }
        $printable = DesignerModel::printable()->where('designer_profile_id', $designer->id)->count();
        $line = $printable > 0
            ? trans_choice('designer.og.printable', $printable, ['n' => $printable], $locale)
            : __('designer.og.portfolio', [], $locale);

        return OgImage::portfolio($designer->display_name, $line, $designer->avatar_path ? Storage::disk('public')->path($designer->avatar_path) : null, $covers);
    }

    /** Cover, name and "we print it from X": the price of one piece in the cheapest material, in the language's currency. */
    private function model(string $slug, string $locale): string
    {
        $card = DesignerModel::printable()->with(['images', 'profile'])->where('slug', $slug)->firstOrFail();
        abort_unless(in_array($locale, $card->locales(), true), 404);
        $quote = app(ModelPricing::class)->quote($card, 1, null, null, $locale === Locales::DEFAULT ? Money::CZK : Money::EUR);
        $line = $quote['available']
            ? __('site.og.print_from', ['price' => Money::of($quote['total'], $quote['currency'])->format($locale)], $locale)
            : __('site.og.print_it', [], $locale);
        $cover = $card->cover();

        return OgImage::model($card->title, $line, $cover ? Storage::disk('public')->path($cover->path) : null, $card->profile->display_name);
    }

    private function tool(string $tool, string $locale): string
    {
        $key = str_replace('-', '_', $tool);
        $seo = ToolSeo::texts($key, $locale);
        abort_unless($seo !== null, 404);
        $picture = public_path('img/tools/'.$key.'-800.jpg');

        // the short name the tools list uses as the headline, the page's own title under it
        $name = Lang::hasForLocale('tools.'.$key.'.title', $locale) ? __('tools.'.$key.'.title', [], $locale) : $seo['h1'];

        return OgImage::tool($name, $seo['title'] === $name ? $seo['description'] : $seo['title'], is_file($picture) ? $picture : null);
    }

    private function article(string $slug, string $locale): string
    {
        $post = Post::published()->where('slug', $slug)->firstOrFail();
        abort_unless(in_array($locale, $post->locales(), true), 404);

        return OgImage::article($post->titleIn($locale), __('site.blog.title', [], $locale), $post->coverFile());
    }
}
