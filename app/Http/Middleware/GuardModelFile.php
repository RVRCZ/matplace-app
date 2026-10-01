<?php

namespace App\Http\Middleware;

use App\Models\DesignerModel;
use App\Models\ModelFile;
use App\Support\Track;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stands before every address that hands out the geometry of a model file (the STL, a slicer project, anything
 * derived from it). A designer's file is given only when the designer allowed the download; otherwise only the
 * designer and the farm's operators get it. Everybody else's files (uploads, tool models) pass as before.
 *
 * A download that goes through is counted (events: download) with the card or the file as its subject.
 */
class GuardModelFile
{
    public function handle(Request $request, Closure $next, string $counts = ''): Response
    {
        $file = $request->route('modelFile');
        $card = null;
        if ($file instanceof ModelFile && $file->origin === 'portfolio') {
            $card = DesignerModel::where('model_file_id', $file->id)->first();
            $user = $request->user();
            $trusted = $user && ($user->id === $file->owner_user_id || $user->isAdmin());
            if (! $trusted && ! ($card && $card->download_allowed && $card->getAttribute('visible'))) {
                abort(403, 'This file is not offered for download.');
            }
        }
        $response = $next($request);

        // "project" always is a download; the plain STL only when the link says so (the 3D preview fetches it too)
        if ($file instanceof ModelFile && $response->isSuccessful() && ($counts === 'project' || ($counts === 'stl' && $request->boolean('download')))) {
            Track::event('download', $card ?? $file, array_filter(['kind' => $counts === 'project' ? '3mf' : 'stl', 'tool' => $file->origin === 'tool' ? $file->kind() : null, 'printer' => $request->query('printer')]));
        }

        return $response;
    }
}
