<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ModelFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModelFileController extends Controller
{
    /** GET /api/files/{uuid}/model.stl — normalised STL for the viewer and for "I have a printer, download". */
    public function stl(ModelFile $modelFile): StreamedResponse
    {
        abort_unless($modelFile->isReady(), 404);
        $path = $modelFile->absoluteStlPath();
        $name = pathinfo($modelFile->original_name, PATHINFO_FILENAME).'.stl';

        return response()->stream(function () use ($path) {
            $fh = fopen($path, 'rb');
            while (! feof($fh)) {
                echo fread($fh, 1024 * 512);
                flush();
            }
            fclose($fh);
        }, 200, [
            'Content-Type' => 'model/stl',
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => 'inline; filename="'.addslashes($name).'"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
