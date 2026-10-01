<?php

namespace App\Domain\Farm;

use Illuminate\Support\Facades\File;

/**
 * Which Packeta carrier takes a parcel to a country. Two sources, in this order:
 *   1. config/farm.php (`packeta_home_carriers`, `packeta_point_carriers`): what Roman wrote down wins;
 *   2. the carrier list downloaded weekly by `matplace:packeta-carriers` (storage/app/packeta_carriers.json), from
 *      which the carrier named in `packeta_prefer` is chosen, else the only one there is.
 * A country nobody carries to is not offered to the customer at all.
 */
final class CarrierBook
{
    /** Where the downloaded list lives: storage/app/packeta_carriers.json unless config/farm.php says otherwise. */
    public static function path(): string
    {
        $path = (string) (config('farm.packeta_carriers_file') ?: storage_path('app/packeta_carriers.json'));

        // a relative path (PACKETA_CARRIERS_FILE in .env or phpunit.xml) is taken from the project's root
        $absolute = str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z]:/', $path) === 1;

        return $absolute ? $path : base_path($path);
    }

    /** @var list<array{id: int, country: string, type: string, name: string}>|null */
    private ?array $rows = null;

    /** Packeta's own pickup points and boxes: the point's id is the address, no carrier in between. */
    public function internal(string $country): bool
    {
        return in_array(strtoupper($country), (array) config('farm.packeta_internal'), true);
    }

    /** The carrier that delivers to an address in this country; null = we cannot deliver home there. */
    public function home(string $country): ?int
    {
        return $this->pick(strtoupper($country), 'home');
    }

    /**
     * Carriers whose pickup points the picker shows for a country outside Packeta's own network (the preferred one,
     * else all of them).
     *
     * @return list<int>
     */
    public function points(string $country): array
    {
        $country = strtoupper($country);
        $one = $this->pick($country, 'point');
        if ($one !== null) {
            return [$one];
        }

        return array_values(array_map(fn (array $c) => $c['id'], array_filter($this->all(), fn (array $c) => $c['country'] === $country && $c['type'] === 'point')));
    }

    /** A pickup point can be chosen in this country. */
    public function hasPoints(string $country): bool
    {
        return $this->internal($country) || $this->points($country) !== [];
    }

    /** The carrier a partner's pickup point belongs to really carries to that country. */
    public function carries(int $carrierId, string $country, string $type): bool
    {
        $country = strtoupper($country);
        if ((int) (config('farm.packeta_'.$type.'_carriers')[$country] ?? 0) === $carrierId) {
            return true;
        }

        return (bool) array_filter($this->all(), fn (array $c) => $c['id'] === $carrierId && $c['country'] === $country && $c['type'] === $type);
    }

    /** @return list<array{id: int, country: string, type: string, name: string}> */
    public function all(): array
    {
        if ($this->rows === null) {
            $raw = $this->read();
            $this->rows = is_array($raw['carriers'] ?? null) ? array_values($raw['carriers']) : [];
        }

        return $this->rows;
    }

    public function updatedAt(): ?string
    {
        return $this->read()['updated_at'] ?? null;
    }

    /** @return array<string, mixed>|null */
    private function read(): ?array
    {
        $raw = is_file(self::path()) ? json_decode((string) file_get_contents(self::path()), true) : null;

        return is_array($raw) ? $raw : null;
    }

    /** @param  list<array<string, mixed>>  $carriers */
    public function store(array $carriers): void
    {
        File::ensureDirectoryExists(dirname(self::path()));
        File::put(self::path(), (string) json_encode(['updated_at' => now()->toIso8601String(), 'carriers' => array_values($carriers)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->rows = null;
    }

    /**
     * The map as it would be chosen from the list alone (what `--suggest` prints for config/farm.php).
     *
     * @return array{home: array<string, int>, point: array<string, int>}
     */
    public function suggest(): array
    {
        $out = ['home' => [], 'point' => []];
        foreach (array_unique(array_column($this->all(), 'country')) as $country) {
            foreach (['home', 'point'] as $type) {
                if ($type === 'point' && $this->internal($country)) {
                    continue;
                }
                if (($id = $this->fromList($country, $type)) !== null) {
                    $out[$type][$country] = $id;
                }
            }
        }
        ksort($out['home']);
        ksort($out['point']);

        return $out;
    }

    private function pick(string $country, string $type): ?int
    {
        $fixed = (int) (config('farm.packeta_'.$type.'_carriers')[$country] ?? 0);

        return $fixed > 0 ? $fixed : $this->fromList($country, $type);
    }

    private function fromList(string $country, string $type): ?int
    {
        $rows = array_values(array_filter($this->all(), fn (array $c) => $c['country'] === $country && $c['type'] === $type));
        foreach ((array) (config('farm.packeta_prefer')[$country][$type] ?? []) as $word) {
            foreach ($rows as $c) {
                if (stripos($c['name'], (string) $word) !== false) {
                    return $c['id'];
                }
            }
        }

        // nobody preferred: only an unambiguous list decides by itself (for home delivery; points show them all)
        return $type === 'home' && count($rows) === 1 ? $rows[0]['id'] : null;
    }
}
