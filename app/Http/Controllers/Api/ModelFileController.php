<?php

namespace App\Http\Controllers\Api;

use App\Domain\Generation\PedestalChanger;
use App\Engines\Contracts\ProjectExporter;
use App\Engines\DTO\SliceParams;
use App\Engines\Exceptions\EngineException;
use App\Http\Controllers\Controller;
use App\Models\ModelFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ModelFileController extends Controller
{
    /** GET /api/printers — printers a ready-made slicer project can be made for, grouped for a two-step picker */
    public function printers(ProjectExporter $exporter): JsonResponse
    {
        $groups = [];
        foreach ($exporter->printers() as $p) {
            $slicer = $p['slicer'] ?? 'orca';
            // Prusa owners choose by the program they use; every other brand has one entry
            $label = $p['vendor_label'] === 'Prusa' ? 'Prusa ('.($slicer === 'prusaslicer' ? 'PrusaSlicer' : 'OrcaSlicer').')' : $p['vendor_label'];
            $groups[$label][] = ['id' => $p['id'], 'model' => $p['model'], 'slicer' => $slicer, 'bed' => $p['bed'], 'materials' => $p['materials']];
        }
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        return response()->json(['vendors' => array_map(fn ($v, $list) => ['vendor' => $v, 'printers' => $list], array_keys($groups), $groups)])
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /** GET /api/files/{uuid}/project.3mf?printer=…&material=…&quality=…&infill=…&supports=…&scale=… */
    public function project(Request $request, ModelFile $modelFile, ProjectExporter $exporter): BinaryFileResponse|JsonResponse
    {
        abort_unless($modelFile->isReady(), 404);
        $data = $request->validate([
            'printer' => ['required', 'string', 'max:80'],
            'material' => ['nullable', 'string', 'max:10'],
            'quality' => ['nullable', 'in:draft,standard,fine'],
            'infill' => ['nullable', 'integer', 'min:0', 'max:100'],
            'supports' => ['nullable'],
            'scale' => ['nullable', 'numeric', 'min:0.1', 'max:10'],
        ]);
        $hints = $modelFile->printHints();
        // "auto" supports become the tool's recommendation: a project file has to say yes or no
        $supports = $data['supports'] ?? 'auto';
        $data['supports'] = in_array($supports, ['auto', '', null], true) ? (int) ($hints['supports'] ?? false) : (int) (bool) $supports;
        $params = SliceParams::fromArray(['tree' => $modelFile->wantsTreeSupports()] + $data);
        try {
            $path = $exporter->export($modelFile->absoluteStlPath(), $data['printer'], $params, ['kind' => $modelFile->kind()]);
        } catch (EngineException $e) {
            return response()->json(['error' => 'export_failed', 'message' => $e->getMessage()], 422);
        }
        $name = Str::slug(pathinfo($modelFile->original_name, PATHINFO_FILENAME)) ?: 'model';

        return response()->download($path, $name.'-'.$data['printer'].'.3mf', ['Content-Type' => 'model/3mf'])->deleteFileAfterSend(true);
    }

    /** POST /api/files/{uuid}/pedestal — another base, name or front for a generated bust or figure; answers with the new file */
    public function pedestal(Request $request, ModelFile $modelFile, PedestalChanger $changer): JsonResponse
    {
        abort_unless($modelFile->isReady() && $changer->state($modelFile) !== null, 404);
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', PedestalChanger::TYPES)],
            'name' => ['nullable', 'string', 'max:24'],
            'dedication' => ['nullable', 'string', 'max:40'],
            'front' => ['nullable', 'in:'.implode(',', PedestalChanger::FRONTS)],
            'sink' => ['nullable', 'integer', 'in:'.implode(',', PedestalChanger::SINKS)],
            'tidy' => ['nullable', 'boolean'],
        ]);
        try {
            $new = $changer->change($modelFile, $data, $data['front'] ?? 'keep', (int) ($data['sink'] ?? 0), (bool) ($data['tidy'] ?? true));
        } catch (\RuntimeException) {
            return response()->json(['error' => 'pedestal_failed'], 422);
        }

        return response()->json(['file' => UploadController::describe($new)], 201);
    }

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
