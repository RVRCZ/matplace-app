<?php

namespace App\Engines\Search;

use App\Engines\Contracts\ModelSearch;
use App\Engines\DTO\ModelCandidate;
use App\Engines\DTO\SearchOptions;
use App\Engines\DTO\SearchResultSet;
use App\Models\CatalogModel;
use App\Models\DesignerModel;
use Illuminate\Support\Facades\DB;

/**
 * What we have ourselves, in this order:
 *  1. designers' models the farm prints (origin "matplace": the card opens /models/{slug});
 *  2. the inspiration catalogue (origin "inspiration": the card opens /model/{slug}, which links to the source).
 * Full text on MariaDB; LIKE on other drivers (sqlite in tests).
 */
final class LocalCatalogSearch implements ModelSearch
{
    public function source(): string
    {
        return 'local';
    }

    public function byText(string $query, SearchOptions $options): SearchResultSet
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return new SearchResultSet([], $query, ['local']);
        }
        $terms = array_values(array_filter(preg_split('/\s+/', $query) ?: [], fn ($t) => mb_strlen($t) >= 2));
        $printable = $options->requireFile ? [] : $this->printable($terms, $options->limit);

        $q = CatalogModel::query()->where('visible', true);
        $rows = collect();
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $boolean = implode(' ', array_map(fn ($t) => '+'.preg_replace('/[+\-<>()~*"@]/', '', $t).'*', $terms));
            $rows = (clone $q)->selectRaw('catalog_models.*, MATCH(title, description, keywords) AGAINST (? IN NATURAL LANGUAGE MODE) AS score', [$query])
                ->whereRaw('MATCH(title, description, keywords) AGAINST (? IN BOOLEAN MODE)', [$boolean])
                ->orderByDesc('score')->limit($options->limit)->get();
            if ($rows->isEmpty()) {
                $rows = (clone $q)->selectRaw('catalog_models.*, MATCH(title, description, keywords) AGAINST (? IN NATURAL LANGUAGE MODE) AS score', [$query])
                    ->whereRaw('MATCH(title, description, keywords) AGAINST (?)', [$query])
                    ->orderByDesc('score')->limit($options->limit)->get();
            }
        }
        if ($rows->isEmpty()) {
            foreach ($terms as $t) {
                $q->where(fn ($w) => $w->where('title', 'like', "%{$t}%")->orWhere('keywords', 'like', "%{$t}%")->orWhere('description', 'like', "%{$t}%"));
            }
            $rows = $q->limit($options->limit)->get()->map(function ($m) use ($query) {
                $m->score = str_contains(mb_strtolower($m->title), mb_strtolower($query)) ? 2.0 : 1.0;

                return $m;
            });
        }

        $max = max(1e-9, (float) $rows->max('score'));
        // a card has to lead somewhere: to its own page, to the source's page, or to a file we hold
        $rows = $rows->filter(fn (CatalogModel $m) => $m->slug || $m->hasWebLink() || $m->file_available)->values();
        // a model whose author offers it for printing is already in the first group
        $claimed = array_flip(array_filter(array_map(fn (ModelCandidate $c) => $c->localModelId, $printable)));
        $rows = $rows->reject(fn (CatalogModel $m) => isset($claimed[$m->id]))->values();
        // inspiration always ranks below what can be printed here
        $items = $rows->map(fn (CatalogModel $m) => $m->toCandidate(round(0.89 * ((float) $m->score) / $max, 3)))->all();
        if ($options->requireFile) {
            $items = array_values(array_filter($items, fn (ModelCandidate $c) => $c->fileAvailable));
        }

        return new SearchResultSet(array_slice(array_merge($printable, $items), 0, $options->limit), $query, ['local']);
    }

    /**
     * Designers' cards the farm can print whose title or tags hold every word of the query.
     *
     * @param  list<string>  $terms
     * @return list<ModelCandidate>
     */
    private function printable(array $terms, int $limit): array
    {
        if (! $terms) {
            return [];
        }
        $cards = DesignerModel::printable()->with(['images', 'modelFile', 'profile']);
        foreach ($terms as $t) {
            $cards->where(fn ($w) => $w->where('designer_models.title', 'like', "%{$t}%")->orWhere('designer_models.tags', 'like', "%{$t}%")->orWhere('designer_models.description', 'like', "%{$t}%"));
        }

        return $cards->orderByDesc('order_count')->limit($limit)->get()->map(fn (DesignerModel $card) => new ModelCandidate(
            source: 'local',
            externalId: 'd'.$card->id,
            title: $card->title,
            previewUrl: $card->coverUrl(),
            externalUrl: $card->publicUrl(),
            license: null,
            authorName: $card->profile->display_name,
            localModelId: $card->catalog_model_id,
            score: 1.0,
            fileAvailable: false,
            origin: 'matplace',
        ))->all();
    }

    public function byImage(string $imagePath, SearchOptions $options): SearchResultSet
    {
        // image → description happens in the composite (VisionDescriber); the catalogue only understands text
        return new SearchResultSet([], '', ['local']);
    }

    public function fetch(string $externalId): ?ModelCandidate
    {
        return CatalogModel::find((int) $externalId)?->toCandidate(1.0);
    }
}
