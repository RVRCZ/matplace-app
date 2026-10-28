<?php

namespace App\Domain\Catalog;

use App\Models\CatalogModel;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * The title and the description of an inspiration page for search engines (docs/AN.md §16). Search Console showed the
 * pages with impressions and hardly a click while they said only the source's name: now they say what we offer – the
 * price of a print in three materials and the print time once the model's file is sliced, else that we work out the
 * price, print and ship. Within the lengths search engines show (the name cut first, the description last).
 */
final class InspirationSeo
{
    public const NAME_MAX = 45;

    public const DESCRIPTION_MAX = 155;

    /**
     * @param  array{rows: list<array{code: string, minutes: int, total_text: string}>}|null  $sliced  ModelPricing::sliced()
     * @param  string  $text  the page's own text, added to the plain description while there is room
     * @return array{title: string, description: string}
     */
    public static function texts(CatalogModel $model, ?array $sliced, string $text = ''): array
    {
        $name = Str::limit(trim(preg_replace('/\s+/', ' ', (string) $model->title)), self::NAME_MAX, '…');
        $license = $model->license && Lang::has('models.license.'.$model->license) ? __('models.license.'.$model->license) : __('models.license.unknown');
        if ($sliced && ! empty($sliced['rows'])) {
            $prices = implode(', ', array_map(fn (array $r) => $r['total_text'].' ('.$r['code'].')', $sliced['rows']));
            $minutes = (int) min(array_column($sliced['rows'], 'minutes'));
            $description = __('models.inspiration.seo_desc_sliced', ['name' => $name, 'prices' => $prices, 'time' => self::duration($minutes), 'license' => $license]);
        } else {
            $description = __('models.inspiration.seo_desc_plain', ['name' => $name]);
            $excerpt = trim((string) preg_replace('/\s+/', ' ', $text));
            if ($excerpt !== '' && mb_strlen($description) < self::DESCRIPTION_MAX - 40) {
                $description .= ' '.$excerpt;
            }
        }

        return [
            'title' => __('models.inspiration.seo_title_one', ['name' => $name]).' · matplace',
            'description' => Str::limit($description, self::DESCRIPTION_MAX - 3, '…'),
        ];
    }

    /** "1 h 47 min", "12 min". */
    private static function duration(int $minutes): string
    {
        $minutes = max(1, $minutes);

        return $minutes >= 60 ? intdiv($minutes, 60).' h '.($minutes % 60).' min' : $minutes.' min';
    }
}
