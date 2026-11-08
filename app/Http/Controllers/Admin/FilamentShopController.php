<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Farm\FilamentFromShop;
use App\Domain\Farm\Shop\ShopRefused;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The filament card from an e-shop link (TODO #71, docs/AL.md §21): the loading page and the materials post a link,
 * get a job id, ask for its state every 2 s, show the card, and post the admin's corrected card back to be saved.
 * Everything answers JSON for the page's script; the pictures the model looked at are served from the job's folder.
 */
class FilamentShopController extends Controller
{
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:500'],
            'printer_id' => ['nullable', 'integer', 'exists:farm_printers,id'],
            'slot' => ['nullable', 'integer', 'min:0', 'max:31'],
            'color_id' => ['nullable', 'integer', 'exists:farm_colors,id'],   // "Načíst znovu" on a colour the catalogue has (docs/AL.md §21.8)
        ]);
        try {
            $entry = FilamentFromShop::start($data['url'], isset($data['printer_id']) ? (int) $data['printer_id'] : null, isset($data['slot']) ? (int) $data['slot'] : null, $request->user()?->id, isset($data['color_id']) ? (int) $data['color_id'] : null);
        } catch (ShopRefused $e) {
            return response()->json(['message' => $e->reason === ShopRefused::TAKEN ? $e->getMessage() : __('farm.admin.shop.refused.'.$e->reason, ['status' => $e->status])], 422);
        }

        return response()->json($this->shape($entry));
    }

    public function status(string $id): JsonResponse
    {
        $entry = FilamentFromShop::get($id);
        if (! $entry) {
            abort(404);
        }

        return response()->json($this->shape($entry));
    }

    /** A downsized copy of one of the page's pictures, for the card (never the shop's own address). */
    public function image(string $id, int $n): BinaryFileResponse
    {
        $path = FilamentFromShop::imagePath($id, $n);
        if (! FilamentFromShop::get($id) || ! is_file($path)) {
            abort(404);
        }

        return response()->file($path, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=600']);
    }

    public function save(Request $request, FilamentFromShop $agent, string $id): JsonResponse
    {
        $entry = FilamentFromShop::get($id);
        if (! $entry) {
            abort(404);
        }
        if ($entry['status'] !== FilamentFromShop::STATUS_DONE) {
            return response()->json(['message' => __('farm.admin.shop.status.'.$entry['status'])], 422);
        }
        try {
            $r = $agent->save($entry, $request->all());
        } catch (ValidationException $e) {
            return response()->json(['message' => (string) collect($e->errors())->flatten()->first(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'ok' => true, 'created' => $r['created'], 'notices' => $r['notices'], 'filled' => $r['filled'] ?? [],
            'color' => ['id' => $r['color']->id, 'label' => $r['color']->fullLabel(), 'kind_id' => $r['color']->farm_material_id],
            'slot' => $r['slot'] ? ['printer_id' => $r['slot']->farm_printer_id, 'slot' => $r['slot']->slot, 'remaining_g' => $r['slot']->remaining_g, 'enabled' => $r['slot']->enabled] : null,
        ]);
    }

    public function cancel(string $id): JsonResponse
    {
        FilamentFromShop::forget($id);

        return response()->json(['ok' => true]);
    }

    /** The entry as the page gets it: no user, no record for the database, the pictures as our own addresses. */
    private function shape(array $entry): array
    {
        $id = (string) $entry['id'];

        return [
            'id' => $id, 'status' => $entry['status'], 'message' => $entry['message'], 'url' => $entry['url'], 'host' => $entry['host'],
            'printer_id' => $entry['printer_id'], 'slot' => $entry['slot'], 'warnings' => array_values((array) $entry['warnings']),
            'existing' => $entry['existing'], 'card' => $entry['card'], 'color_id' => $entry['color_id'] ?? null, 'refresh' => $entry['refresh'] ?? null,
            'images' => array_map(fn (array $im) => $im + ['url' => route('admin.farm.colors.from_url.image', ['id' => $id, 'n' => $im['n']])], (array) $entry['images']),
        ];
    }
}
