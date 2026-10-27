<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Farm\FarmRefusal;
use App\Domain\Farm\PrintPhotos;
use App\Domain\Farm\TestPhotoJudge;
use App\Domain\Farm\TestPhotos;
use App\Http\Controllers\Controller;
use App\Jobs\AddFinishPhotoToVideo;
use App\Jobs\ComposePrintPhoto;
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
 * the test on the tuning page, and read by the judge that prefills the evaluation form. A customer's print or a
 * showcase photographed the same way gets the photo its videos end with made presentable (ComposePrintPhoto: the
 * background off, the studio backdrop, every size), like the upload on the order page.
 */
class FarmTestPhotoController extends Controller
{
    public function __construct(private readonly TestPhotos $photos, private readonly PrintPhotos $prints) {}

    public function store(Request $request, FarmOrder $order): RedirectResponse|JsonResponse
    {
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
        // a customer's print or a showcase: the photo its videos end with is made presentable first (ComposePrintPhoto,
        // which then has the videos built again) – unless the piece already has that very photo composed
        $composing = $added && ! $order->isTest() && $this->compose($order);
        $message = $error ?? 'Uloženo '.$added.' fotek.'.($composing ? ' Fotka do videa se připravuje (pozadí, podklad); videa se pak přestaví.' : '');
        if ($request->wantsJson()) {
            return response()->json(['ok' => $error === null, 'added' => $added, 'message' => $message, 'photos' => count($this->photos->all($order)), 'composing' => $composing], $error && ! $added ? 422 : 200);
        }

        return $this->back($order)->with($error ? 'error' : 'status', $message);
    }

    public function show(FarmOrder $order, int $index, Request $request): BinaryFileResponse
    {
        $photo = $this->photos->all($order)[$index] ?? abort(404);
        $file = $request->boolean('thumb') && ! empty($photo['thumb']) ? $photo['thumb'] : $photo['file'];
        $disk = Storage::disk(config('farm.disk'));
        abort_unless($disk->exists($file), 404);

        return response()->file($disk->path($file), ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=86400']);
    }

    public function destroy(Request $request, FarmOrder $order, int $index): RedirectResponse
    {
        $gone = $this->photos->all($order)[$index]['file'] ?? null;
        $this->photos->remove($order, $index);
        // the end of a customer's video may have shown this very photo: made again from the next one, or without any
        if (! $order->isTest() && $gone !== null) {
            $this->photoGone($order, $gone);
        }
        $to = str_contains((string) $request->headers->get('referer'), '/photobox') ? redirect()->route('admin.farm.photobox', ['order' => $order->token]) : $this->back($order);

        return $to->with('status', 'Fotka smazána.');
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
        // test prints for the evaluation, customer prints and showcases for the end of their video
        $tests = FarmOrder::whereIn('status', [FarmOrder::STATUS_DONE, FarmOrder::STATUS_HANDED_OVER, FarmOrder::STATUS_PRINTING])
            ->with(['printer', 'color', 'material'])->latest('id')->limit(30)->get();
        $order = $request->query('order') ? $tests->firstWhere('token', $request->query('order')) : $tests->first();

        return view('admin.farm.photobox', ['tests' => $tests, 'order' => $order, 'photos' => $order ? $this->photos->all($order) : [],
            'finish' => $order && ! $order->isTest() ? $this->photos->finishIndex($order) : null]);
    }

    /**
     * The composed photo of the piece follows the photo box: made from the photo the videos end with (finishFile)
     * whenever that is not the one it was made from, or the background is still on it (rembg had no model that day).
     * Another view of the same piece changes nothing. Says whether a job was queued.
     */
    private function compose(FarmOrder $order): bool
    {
        $file = $this->photos->finishFile($order);
        if ($file === null || ($order->hasPhoto() && ! empty($order->photo_meta['cut']) && ($order->photo_meta['source'] ?? null) === $file)) {
            return false;
        }
        ComposePrintPhoto::dispatch($order->id, $file);

        return true;
    }

    private function photoGone(FarmOrder $order, string $gone): void
    {
        if ($order->hasPhoto() && ($order->photo_meta['source'] ?? null) !== $gone) {
            return;   // the composed photo came from another file (the admin's upload): the videos keep it
        }
        if ($this->compose($order)) {
            return;
        }
        if ($order->hasPhoto()) {
            $this->prints->remove($order);
        }
        if ($order->timelapse_path) {
            AddFinishPhotoToVideo::dispatch($order->id);
        }
    }

    private function back(FarmOrder $order): RedirectResponse
    {
        return $order->farm_printer_material_id
            ? redirect()->to(route('admin.farm.tuning.edit', $order->farm_printer_material_id).'#test-'.$order->id)
            : redirect()->route('admin.farm.orders.show', $order);
    }
}
