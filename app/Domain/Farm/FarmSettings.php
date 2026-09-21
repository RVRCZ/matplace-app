<?php

namespace App\Domain\Farm;

use App\Models\FarmSetting;

/** Farm settings: the admin's value from the database, else the default from config/farm.php. */
final class FarmSettings
{
    /** @var array<string,mixed>|null */
    private ?array $overrides = null;

    public function get(string $key): mixed
    {
        $all = $this->overrides();

        return array_key_exists($key, $all) ? $all[$key] : config('farm.settings.'.$key);
    }

    /** @return array<string,mixed> every known setting with its effective value */
    public function all(): array
    {
        return array_merge((array) config('farm.settings'), array_intersect_key($this->overrides(), (array) config('farm.settings')));
    }

    public function set(string $key, mixed $value): void
    {
        if (! array_key_exists($key, (array) config('farm.settings'))) {
            throw new \InvalidArgumentException("Unknown farm setting '{$key}'.");
        }
        FarmSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        $this->overrides = null;
    }

    public function infillFor(string $strength): int
    {
        $s = (array) $this->get('strengths');

        return (int) ($s[$strength]['infill'] ?? $s['standard']['infill'] ?? 15);
    }

    public function layerFor(string $quality): float
    {
        $q = (array) $this->get('qualities');

        return (float) ($q[$quality]['layer_mm'] ?? 0.2);
    }

    private function overrides(): array
    {
        return $this->overrides ??= FarmSetting::all()->mapWithKeys(fn (FarmSetting $s) => [$s->key => $s->value])->all();
    }
}
