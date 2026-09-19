<?php

namespace App\Engines\Search;

use App\Engines\Contracts\ModelSearch;
use App\Engines\DTO\ModelCandidate;
use App\Engines\DTO\SearchOptions;
use App\Engines\DTO\SearchResultSet;
use App\Models\CatalogModel;
use Illuminate\Support\Facades\DB;

/** Full-text search over catalog_models (MariaDB FULLTEXT; LIKE fallback on other drivers, e.g. sqlite in tests). */
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
        $q = CatalogModel::query()->where('active', true);
        $rows = collect();

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $terms = array_filter(preg_split('/\s+/', $query), fn ($t) => mb_strlen($t) >= 2);
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
            foreach (array_filter(preg_split('/\s+/', $query), fn ($t) => mb_strlen($t) >= 2) as $t) {
                $q->where(fn ($w) => $w->where('title', 'like', "%{$t}%")->orWhere('keywords', 'like', "%{$t}%")->orWhere('description', 'like', "%{$t}%"));
            }
            $rows = $q->limit($options->limit)->get()->map(function ($m) use ($query) {
                $m->score = str_contains(mb_strtolower($m->title), mb_strtolower($query)) ? 2.0 : 1.0;

                return $m;
            });
        }

        $max = max(1e-9, (float) $rows->max('score'));
        $items = $rows->map(fn (CatalogModel $m) => $m->toCandidate(round(((float) $m->score) / $max, 3)))->all();
        if ($options->requireFile) {
            $items = array_values(array_filter($items, fn (ModelCandidate $c) => $c->fileAvailable));
        }

        return new SearchResultSet($items, $query, ['local']);
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
