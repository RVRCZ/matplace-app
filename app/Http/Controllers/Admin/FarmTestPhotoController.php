<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Farm\FarmRefusal;
use App\Domain\Farm\TestPhotoJudge;
use App\Domain\Farm\TestPhotos;
use App\Http\Controllers\Controller;
use App\Jobs\JudgeTestPhotos;
use App\Models\FarmOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Photos of printed test objects: uploaded from a phone or taken in the photo box (three fixed cameras), shown with
 * the test on the tuning page, and read by the judge that prefills the evaluation form.
 */
class FarmTestPhotoController extends Controller
{
    public function __construct(private readonly TestPhotos $photos) {}

    public function store(Request $request, FarmOrder $order): RedirectResponse|JsonResponse
    {
        abort_unless($order->isTest(), 404);
        $request->validate([
            'photos' => ['required', 'array', 'max:'.TestPhotos::MAX],
            'photos.*' => ['file', 'max:30720'],
            'views' => ['nullable', 'array'],
            'views.*' => ['nullable', Rule::in(TestPhotos::VIEWS)],
        ]);
        $added = 0;
        $error = null;
        foreach ($request->file('photos') as $i => $file) {
            try {
                $this->photos->add($order, $file->getRealPath(), (string) ($request->input('views.'.$i) ?? 'phone'));
                $added++;
                $order->refresh();
            } catch (FarmRefusal $e) {
                $error = $e->text();
            }
        }
        if ($request->wantsJson()) {
            return response()->json(['ok' => $error === null, 'added' => $added, 'message' => $error ?? 'Uloženo '.$added.' fotek.', 'photos' => count($this->photos->all($order))], $error && ! $added ? 422 : 200);
        }

        return $this->back($order)->with($error ? 'error' : 'status', $error ?? 'Uloženo '.$added.' fotek.');
    }

    public function show(FarmOrder $order, int $index, Request $request): BinaryFileResponse
    {
        $photo = $this->photos->all($order)[$index] ?? abort(404);
        $file = $request->boolean('thumb') && ! empty($photo['thumb']) ? $photo['thumb'] : $photo['file'];
        $disk = Storage::disk(config('farm.disk'));
        abort_unless($disk->exists($file), 404);

        return response()->file($disk->path($file), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=86400']);
    }

    public function destroy(FarmOrder $order, int $index): RedirectResponse
    {
        $this->photos->remove($order, $index);

        return $this->back($order)->with('status', 'Fotka smazána.');
    }

    public function judge(FarmOrder $order, TestPhotoJudge $judge): RedirectResponse
    {
        abort_unless($order->isTest(), 404);
        if (! $judge->available()) {
            return $this->back($order)->with('error', 'Chybí klíč k AI (ANTHROPIC_API_KEY).');
        }
        if (! $this->photos->all($order)) {
            return $this->back($order)->with('error', 'Nejdřív nahrajte fotky testu.');
        }
        $order->forceFill(['test_params' => ['ai' => ['status' => 'queued', 'at' => now()->toIso8601String()]] + (array) $order->test_params])->save();
        JudgeTestPhotos::dispatch($order->id);

        return $this->back($order)->with('status', 'Fotky se vyhodnocují, trvá to 1–3 minuty. Obnovte stránku.');
    }

    /** The photo box: three fixed cameras on the PC next to it, one click takes all three for the chosen test. */
    public function box(Request $request): View
    {
        $tests = FarmOrder::where('kind', FarmOrder::KIND_TEST)->whereIn('status', [FarmOrder::STATUS_DONE, FarmOrder::STATUS_HANDED_OVER, FarmOrder::STATUS_PRINTING])
            ->with(['printer', 'color', 'material'])->latest('id')->limit(20)->get();
        $order = $request->query('order') ? $tests->firstWhere('token', $request->query('order')) : $tests->first();

        return view('admin.farm.photobox', ['tests' => $tests, 'order' => $order, 'photos' => $order ? $this->photos->all($order) : []]);
    }

    private function back(FarmOrder $order): RedirectResponse
    {
        return $order->farm_printer_material_id
            ? redirect()->to(route('admin.farm.tuning.edit', $order->farm_printer_material_id).'#test-'.$order->id)
            : redirect()->route('admin.farm.orders.show', $order);
    }
}
