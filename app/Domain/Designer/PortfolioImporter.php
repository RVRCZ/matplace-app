<?php

namespace App\Domain\Designer;

use App\Engines\Import\ImportedModel;
use App\Engines\Import\ImportFailed;
use App\Engines\Import\Sources;
use App\Engines\Translate\Translator;
use App\Jobs\ImportDesignerModel;
use App\Models\CatalogModel;
use App\Models\DesignerImport;
use App\Models\DesignerModel;
use App\Models\DesignerProfile;
use App\Support\Locales;
use Illuminate\Support\Facades\Log;

/**
 * Bringing a designer's cards over from Printables or MakerWorld: title, description (translated into the languages
 * it is missing in), pictures, licence, remix parent. Never the files: both sites give them only to their own
 * logged-in users, so the designer uploads them here.
 */
final class PortfolioImporter
{
    public function __construct(
        private readonly Sources $sources,
        private readonly Translator $translator,
        private readonly DesignerImages $images,
        private readonly DesignerProfiles $profiles,
    ) {}

    /**
     * Models of the verified author at the source, each marked when it is already in the portfolio.
     * Null when the source has no list we can read (the designer then pastes addresses).
     *
     * @return list<array{id: string, url: string, title: string, image: ?string, is_remix: bool, imported: bool}>|null
     */
    public function candidates(DesignerProfile $profile, string $sourceKey, int $limit = 300): ?array
    {
        $authorId = $profile->externalIdOn($sourceKey);
        if (! $authorId) {
            return null;
        }
        $list = $this->sources->get($sourceKey)->models($authorId, $limit);
        if ($list === null) {
            return null;
        }
        $have = DesignerModel::where('source', $sourceKey)->whereIn('external_id', array_column($list, 'id'))->pluck('external_id')->flip();

        return array_map(fn (array $m) => $m + ['imported' => isset($have[$m['id']])], $list);
    }

    /**
     * Start an import of the given models (ids or addresses at the source). One queue job per model.
     *
     * @param  list<string>  $refs
     */
    public function start(DesignerProfile $profile, string $sourceKey, array $refs): DesignerImport
    {
        $source = $this->sources->get($sourceKey);
        $items = [];
        foreach ($refs as $ref) {
            $ref = trim((string) $ref);
            $id = ctype_digit($ref) ? $ref : $source->modelId($ref);
            if ($id && ! isset($items[$id])) {
                $items[$id] = ['id' => $id, 'url' => ctype_digit($ref) ? null : $ref, 'title' => null, 'state' => DesignerImport::ITEM_WAITING, 'note' => null, 'model_id' => null];
            }
        }
        $items = array_slice(array_values($items), 0, DesignerImport::MAX_ITEMS);
        $import = DesignerImport::create([
            'designer_profile_id' => $profile->id, 'source' => $sourceKey, 'total' => count($items), 'items' => $items,
            'status' => $items ? DesignerImport::STATUS_PENDING : DesignerImport::STATUS_DONE,
        ]);
        foreach (array_keys($items) as $index) {
            ImportDesignerModel::dispatch($import->id, $index);
        }

        return $import;
    }

    /** One item of an import. Never throws: the outcome is written to the import. */
    public function importItem(DesignerImport $import, int $index): void
    {
        $item = $import->items[$index] ?? null;
        $profile = $import->profile;
        if (! $item || ! $profile || ($item['state'] ?? '') !== DesignerImport::ITEM_WAITING) {
            return;
        }
        try {
            if ($profile->models()->count() >= DesignerProfile::MAX_MODELS) {
                $import->finishItem($index, DesignerImport::ITEM_FAILED, 'limit');

                return;
            }
            $remote = $this->sources->get($import->source)->fetch((string) $item['id']);
            $this->titled($import, $index, $remote);
            // one card per address, whoever imported it first
            if ($existing = DesignerModel::where('source', $remote->source)->where('external_id', $remote->id)->first()) {
                $import->finishItem($index, DesignerImport::ITEM_SKIPPED, $existing->designer_profile_id === $profile->id ? 'duplicate' : 'taken', $existing->id);

                return;
            }
            // the card must come from the author who proved the account
            if ($remote->authorId === '' || $remote->authorId !== $profile->externalIdOn($import->source)) {
                $import->finishItem($index, DesignerImport::ITEM_FAILED, 'not_yours');

                return;
            }
            $model = $this->createCard($profile, $remote);
            $import->finishItem($index, DesignerImport::ITEM_IMPORTED, null, $model->id);
        } catch (ImportFailed $e) {
            $import->finishItem($index, DesignerImport::ITEM_FAILED, $e->reason);
        } catch (\Throwable $e) {
            Log::warning('Designer import item failed', ['import' => $import->id, 'item' => $item['id'] ?? null, 'error' => $e->getMessage()]);
            $import->finishItem($index, DesignerImport::ITEM_FAILED, 'unreadable');
        }
    }

    public function createCard(DesignerProfile $profile, ImportedModel $remote): DesignerModel
    {
        $text = $remote->descriptionText();
        [$locale, $description] = $this->describe($text, ['subject_type' => 'designer_model', 'user_id' => $profile->user_id]);
        $model = DesignerModel::create([
            'designer_profile_id' => $profile->id,
            'title' => mb_substr($remote->title, 0, 200),
            'slug' => DesignerModel::makeSlug($remote->title),
            'description' => $description,
            'source' => $remote->source,
            'source_locale' => $locale,
            'external_url' => $remote->url,
            'external_id' => $remote->id,
            'license_source' => mb_substr($remote->license, 0, 200) ?: null,
            'is_remix' => $remote->isRemix,
            'remix_source_url' => $remote->remixSourceUrl ? mb_substr($remote->remixSourceUrl, 0, 500) : null,
            'royalty_czk' => $profile->default_royalty_czk,
            'visible' => true,
            'tags' => array_slice($remote->tags, 0, 20),
            'source_files' => array_slice($remote->files, 0, 30),
            'catalog_model_id' => CatalogModel::where('source', $remote->source)->where('external_id', $remote->id)->value('id'),
        ]);
        foreach (array_slice($remote->images, 0, DesignerModel::MAX_IMAGES) as $url) {
            $this->images->addFromUrl($model, $url);
        }
        $this->profiles->cardShown($model);

        return $model;
    }

    /**
     * The description in every language of the site: the original under the language it was written in,
     * translations for the rest. Only the missing languages are asked for. When translation is not available
     * the original stands alone, under the language it most likely is.
     *
     * @param  array<string, string>  $have  texts already written, by language
     * @return array{0: ?string, 1: array<string, string>} [language of the original, texts by language]
     */
    public function describe(string $text, array $context = [], array $have = [], ?string $from = null): array
    {
        $have = array_filter(array_map('trim', $have), fn ($t) => $t !== '');
        $text = trim($text);
        if ($text === '' && ! $have) {
            return [null, []];
        }
        $source = $from && isset($have[$from]) ? $from : ($have ? array_key_first($have) : null);
        $missing = array_values(array_diff(Locales::SUPPORTED, array_keys($have)));
        if (! $missing) {
            return [$source, $have];
        }
        try {
            if (! $this->translator->available()) {
                throw new \RuntimeException('no translator');
            }
            $result = $this->translator->translate($source ? $have[$source] : $text, $missing, $source, Translator::STYLE_FAITHFUL, ['kind' => 'translate'] + $context);
            $out = $have + $result->texts;
            if (! $source && Locales::supported($result->from)) {
                $out[$result->from] = $text;   // the original itself, untouched
            }

            return [$source ?? $result->from, array_intersect_key($out, array_flip(Locales::SUPPORTED))];
        } catch (\Throwable $e) {
            Log::info('Description left untranslated', ['error' => $e->getMessage()]);

            return [$source ?? Locales::DEFAULT, $have ?: [Locales::DEFAULT => $text]];
        }
    }

    private function titled(DesignerImport $import, int $index, ImportedModel $remote): void
    {
        $items = (array) $import->items;
        $items[$index]['title'] = $remote->title;
        $items[$index]['url'] = $remote->url;
        $import->forceFill(['items' => $items])->save();
    }
}
