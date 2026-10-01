<?php

namespace App\Engines\Import;

/** The sites a portfolio can be imported from, by key. */
final class Sources
{
    public const KEYS = ['printables', 'makerworld'];

    /** @param  array<string, ModelSource>  $sources */
    public function __construct(private readonly array $sources) {}

    public function get(string $key): ModelSource
    {
        return $this->sources[$key] ?? throw new \InvalidArgumentException("Unknown import source [{$key}].");
    }

    public function has(string $key): bool
    {
        return isset($this->sources[$key]);
    }

    /** The source a model address belongs to, with the model's id there. */
    public function forModelUrl(string $url): ?array
    {
        foreach ($this->sources as $source) {
            if ($id = $source->modelId($url)) {
                return [$source, $id];
            }
        }

        return null;
    }
}
