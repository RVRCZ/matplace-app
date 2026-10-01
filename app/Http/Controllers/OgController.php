<?php

namespace App\Http\Controllers;

use App\Models\DesignerModel;
use App\Models\DesignerProfile;
use App\Support\OgImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** /og/…: pictures for link previews, drawn on demand and kept on the disk. */
class OgController extends Controller
{
    public function designer(string $slug, string $locale = 'cs'): BinaryFileResponse
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

        return $this->png(OgImage::portfolio($designer->display_name, $line, $designer->avatar_path ? Storage::disk('public')->path($designer->avatar_path) : null, $covers));
    }

    /** The same picture with its one line of text in English or Spanish. */
    public function designerIn(string $locale, string $slug): BinaryFileResponse
    {
        return $this->designer($slug, $locale);
    }

    private function png(string $path): BinaryFileResponse
    {
        return response()->file($path, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400', 'X-Robots-Tag' => 'noindex']);
    }
}
