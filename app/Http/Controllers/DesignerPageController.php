<?php

namespace App\Http\Controllers;

use App\Models\DesignerModel;
use App\Models\DesignerProfile;
use App\Support\Track;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** /d/{slug}: a designer's public portfolio. */
class DesignerPageController extends Controller
{
    private const PER_PAGE = 48;

    public function show(Request $request, DesignerProfile $designer): View
    {
        // hidden profiles do not exist for the public; the owner sees theirs as a preview
        $owner = $request->user()?->id === $designer->user_id;
        abort_unless($designer->visible || $owner, 404);

        $printable = $request->boolean('printable');
        $cards = $designer->models()->with(['images', 'modelFile'])->where('visible', true)
            ->when($printable, fn ($q) => $q->whereNotNull('model_file_id')->where('file_status', DesignerModel::FILE_READY)->whereNotNull('author_confirmed_at'))
            // what can be printed comes first, then the newest
            ->orderByRaw('CASE WHEN model_file_id IS NOT NULL AND file_status = ? THEN 0 ELSE 1 END', [DesignerModel::FILE_READY])
            ->latest('id')->paginate(self::PER_PAGE)->withQueryString();
        if ($designer->visible) {
            Track::view($designer);
        }

        return view('designer.show', [
            'designer' => $designer,
            'cards' => $cards,
            'printable' => $printable,
            'printableCount' => $designer->models()->where('visible', true)->whereNotNull('model_file_id')->where('file_status', DesignerModel::FILE_READY)->whereNotNull('author_confirmed_at')->count(),
            'preview' => ! $designer->visible,
        ]);
    }
}
