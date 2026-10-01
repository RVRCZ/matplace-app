<?php

namespace App\Engines\Import;

/**
 * A source that lives in memory (ENGINE_IMPORT=fake): tests and local development put profiles and models into it,
 * nothing leaves the machine. It understands the same addresses as the real site it stands for.
 */
final class FakeSource implements ModelSource
{
    /** @var array<string, array<string, SourceProfile>> source → handle → profile */
    private static array $profiles = [];

    /** @var array<string, array<string, ImportedModel>> source → model id → card */
    private static array $models = [];

    /** @var array<string, bool> sources whose author lists are switched off (MakerWorld has none) */
    private static array $unlisted = ['makerworld' => true];

    public function __construct(private readonly string $key, private readonly ModelSource $addresses) {}

    public static function reset(): void
    {
        self::$profiles = self::$models = [];
        self::$unlisted = ['makerworld' => true];
    }

    public static function putProfile(string $source, SourceProfile $profile): void
    {
        self::$profiles[$source][strtolower($profile->handle)] = $profile;
    }

    public static function putModel(ImportedModel $model): void
    {
        self::$models[$model->source][$model->id] = $model;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function modelId(string $url): ?string
    {
        return $this->addresses->modelId($url);
    }

    public function handle(string $url): ?string
    {
        return $this->addresses->handle($url);
    }

    public function profile(string $handle): ?SourceProfile
    {
        if (isset(self::$unlisted[$this->key])) {
            return null;
        }

        return self::$profiles[$this->key][strtolower(ltrim($handle, '@'))] ?? throw new ImportFailed('not_found');
    }

    public function models(string $authorId, int $limit): ?array
    {
        if (isset(self::$unlisted[$this->key])) {
            return null;
        }
        $mine = array_filter(self::$models[$this->key] ?? [], fn (ImportedModel $m) => $m->authorId === $authorId);

        return array_slice(array_values(array_map(fn (ImportedModel $m) => [
            'id' => $m->id, 'url' => $m->url, 'title' => $m->title, 'image' => $m->images[0] ?? null, 'is_remix' => $m->isRemix,
        ], $mine)), 0, $limit);
    }

    public function fetch(string $modelId): ImportedModel
    {
        return self::$models[$this->key][$modelId] ?? throw new ImportFailed('not_found');
    }
}
