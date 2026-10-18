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
        if (! array_key_exists($key, (array) config('farm.settings')) && ! $this->isAiLimit($key)) {
            throw new \InvalidArgumentException("Unknown farm setting '{$key}'.");
        }
        FarmSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        $this->overrides = null;
    }

    /**
     * The daily AI limits (config/ai.php `daily_limits`) the admin set at /admin/ai, stored as `ai.<key>` in the same
     * table as the farm's switches (Roman, 18 Oct 2026: a second phone on the house Wi-Fi hit the guest's one a day).
     *
     * @return array<string, array{value: int, default: int, set: bool}>
     */
    public function aiLimits(): array
    {
        $defaults = (array) ((require config_path('ai.php'))['daily_limits'] ?? []);
        $out = [];
        foreach (array_keys((array) config('ai.daily_limits')) as $key) {
            $over = $this->overrides()['ai.'.$key] ?? null;
            $out[$key] = ['value' => (int) ($over ?? config('ai.daily_limits.'.$key)), 'default' => (int) ($defaults[$key] ?? 0), 'set' => $over !== null];
        }

        return $out;
    }

    /** The admin's AI limits over the config, so every place that reads config('ai.daily_limits.*') sees them. */
    public function applyAiLimits(): void
    {
        foreach (array_keys((array) config('ai.daily_limits')) as $key) {
            $over = $this->overrides()['ai.'.$key] ?? null;
            if ($over !== null) {
                config(['ai.daily_limits.'.$key => (int) $over]);
            }
        }
    }

    private function isAiLimit(string $key): bool
    {
        return str_starts_with($key, 'ai.') && array_key_exists(substr($key, 3), (array) config('ai.daily_limits'));
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
