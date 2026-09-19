<?php

namespace App\Domain\Calculation;

/** Read-only access to config/materials.php with lay-language metadata. */
final class MaterialCatalog
{
    public function __construct(private readonly array $config) {}

    public function has(string $code): bool
    {
        return isset($this->config['items'][strtoupper($code)]);
    }

    public function density(string $code): float
    {
        return (float) ($this->config['items'][strtoupper($code)]['density'] ?? 1.24);
    }

    public function defaultCode(): string
    {
        return $this->config['default'] ?? 'PLA';
    }

    /** Codes that the slicer can process (others are estimated only). */
    public function sliceable(): array
    {
        return array_keys(array_filter($this->config['items'], fn ($m) => $m['slice'] ?? true));
    }

    /** Ordered list for the UI, with translated labels resolved by the caller. */
    public function all(): array
    {
        $items = $this->config['items'];
        uasort($items, fn ($a, $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));
        $out = [];
        foreach ($items as $code => $m) {
            $out[] = [
                'code' => $code,
                'density' => $m['density'],
                'lay' => $m['lay'] ?? [],
                'technology' => $m['technology'] ?? 'fdm',
                'sliceable' => $m['slice'] ?? true,
            ];
        }

        return $out;
    }
}
