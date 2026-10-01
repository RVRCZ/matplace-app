<?php

namespace App\Http\Controllers\Designer;

use App\Domain\Designer\PortfolioImporter;
use App\Engines\Import\ImportFailed;
use App\Engines\Import\Sources;
use App\Http\Controllers\Controller;
use App\Models\DesignerImport;
use App\Models\DesignerProfile;
use App\Support\Track;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Choosing what to bring over from a verified Printables / MakerWorld account, and watching it happen. */
class ImportController extends Controller
{
    use DesignerPages;

    public function form(Request $request, string $source, Sources $sources, PortfolioImporter $importer): View|RedirectResponse
    {
        abort_unless($sources->has($source), 404);
        $profile = $this->profileOf($request);
        if (! $profile->verifiedOn($source)) {
            return redirect()->route('designer.verify', $source);
        }
        $error = null;
        try {
            $candidates = $importer->candidates($profile, $source);
        } catch (ImportFailed $e) {
            // the list is a convenience: without it the designer pastes addresses
            $candidates = null;
            $error = $e->reason;
        }

        return view('designer.import', [
            'profile' => $profile, 'source' => $source, 'candidates' => $candidates, 'listError' => $error,
            'room' => max(0, DesignerProfile::MAX_MODELS - $profile->models()->count()),
            'max' => DesignerImport::MAX_ITEMS,
        ]);
    }

    public function start(Request $request, string $source, Sources $sources, PortfolioImporter $importer): RedirectResponse
    {
        abort_unless($sources->has($source), 404);
        $profile = $this->profileOf($request);
        abort_unless($profile->verifiedOn($source), 403);
        $data = $request->validate([
            'author' => ['accepted'],
            'ids' => ['nullable', 'array', 'max:'.DesignerImport::MAX_ITEMS],
            'ids.*' => ['string', 'regex:/^\d{1,12}$/'],
            'links' => ['nullable', 'string', 'max:40000'],
        ], ['author.accepted' => __('designer.import.author_required')]);

        // pasted addresses: one per line, only models of this very site
        $links = [];
        foreach (preg_split('/\s+/', (string) ($data['links'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $line) {
            if ($sources->get($source)->modelId($line)) {
                $links[] = $line;
            }
        }
        $refs = array_merge((array) ($data['ids'] ?? []), $links);
        if (! $refs) {
            return back()->withInput()->with('error', __('designer.import.nothing'));
        }
        if (count($refs) > DesignerImport::MAX_ITEMS) {
            return back()->withInput()->with('error', __('designer.import.too_many', ['max' => DesignerImport::MAX_ITEMS]));
        }

        Track::event('designer_import', $profile, ['kind' => $source]);

        return redirect()->route('designer.imports.show', $importer->start($profile, $source, $refs)->id);
    }

    public function show(Request $request, int $import): View
    {
        return view('designer.import_show', ['import' => $this->importOf($request, $import)]);
    }

    public function status(Request $request, int $import): JsonResponse
    {
        return response()->json($this->importOf($request, $import)->progress());
    }

    private function importOf(Request $request, int $id): DesignerImport
    {
        return DesignerImport::where('designer_profile_id', $this->profileOf($request)->id)->findOrFail($id);
    }
}
