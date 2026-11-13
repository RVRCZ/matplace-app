<?php

namespace App\Domain\Catalog;

use App\Models\CatalogModel;
use App\Models\FarmOrder;

/**
 * "Takhle jsme to vytiskli" on a model's page of the inspiration catalogue (docs/AP.md §14): photographed prints the
 * farm made of this very model (farm_orders.catalog_model_id). The same orders make the page worth indexing
 * (CatalogModel::printPhotoOrders: paid, finished, photographed, the customer agreed to publishing). The photo shows
 * the print, the material and the date go with it; never the customer or the price they paid.
 */
final class CatalogPhotos
{
    public const MAX = 4;

    /** @return list<array{url: string, webp: ?string, material: ?string, at: ?string}> newest first */
    public static function for(CatalogModel $model): array
    {
        $orders = CatalogModel::printPhotoOrders(FarmOrder::query()->with('material'))
            ->where('farm_orders.catalog_model_id', $model->id)
            ->orderByDesc('farm_orders.finished_at')->orderByDesc('farm_orders.id')->limit(self::MAX * 2)->get();
        $out = [];
        foreach ($orders as $order) {
            if (! ($url = $order->photoUrl())) {
                continue;
            }
            $out[] = ['url' => $url, 'webp' => $order->photoUrl('webp'), 'material' => $order->material?->label(), 'at' => $order->finished_at?->toDateString()];
            if (count($out) >= self::MAX) {
                break;
            }
        }

        return $out;
    }
}
