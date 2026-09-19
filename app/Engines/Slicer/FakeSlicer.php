<?php

namespace App\Engines\Slicer;

use App\Domain\Calculation\RoughEstimator;
use App\Engines\Contracts\Slicer;
use App\Engines\DTO\Dimensions;
use App\Engines\DTO\SliceParams;
use App\Engines\DTO\SliceResult;
use App\Engines\Mesh\StlFile;

/**
 * Deterministic slicer for tests and local development without OrcaSlicer.
 * Uses real geometry (volume, area) and the rough estimator, so numbers are plausible and repeatable.
 */
final class FakeSlicer implements Slicer
{
    public function __construct(private readonly RoughEstimator $rough) {}

    public function name(): string
    {
        return 'fake';
    }

    public function supportedFormats(): array
    {
        return ['stl'];
    }

    public function slice(string $meshPath, SliceParams $params): SliceResult
    {
        $stats = StlFile::stats($meshPath);
        $est = $this->rough->estimate(
            volumeMm3: $stats->volumeMm3,
            areaMm2: $stats->areaMm2,
            materialCode: $params->materialCode,
            quality: $params->quality,
            infillPercent: $params->infillPercent,
            supports: (bool) $params->supports,
            scale: $params->scale,
        );

        return new SliceResult(
            grams: round($est['grams'] * 1.03, 1), // "precise" differs slightly from rough on purpose
            minutes: (int) round($est['minutes'] * 0.97),
            dims: $stats->bbox->scaled($params->scale),
            supportsUsed: (bool) $params->supports,
            gcodePath: null,
            warnings: [],
            raw: ['engine' => 'fake'],
        );
    }

    public function measure(string $meshPath): Dimensions
    {
        return StlFile::stats($meshPath)->bbox;
    }
}
