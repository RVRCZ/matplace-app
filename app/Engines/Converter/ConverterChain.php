<?php

namespace App\Engines\Converter;

use App\Engines\Contracts\FormatConverter;
use App\Engines\DTO\ConvertOptions;
use App\Engines\DTO\ConvertResult;
use App\Engines\Exceptions\ConversionException;

/** Tries registered converters in order; the first that supports the pair wins. */
final class ConverterChain
{
    /** @param  FormatConverter[]  $converters */
    public function __construct(private readonly array $converters) {}

    public function supports(string $fromExt, string $toExt): bool
    {
        return $fromExt === $toExt || $this->find($fromExt, $toExt) !== null;
    }

    public function find(string $fromExt, string $toExt): ?FormatConverter
    {
        foreach ($this->converters as $c) {
            if ($c->supports($fromExt, $toExt)) {
                return $c;
            }
        }

        return null;
    }

    public function convert(string $inPath, string $toExt, string $outPath, ?ConvertOptions $options = null): ConvertResult
    {
        $fromExt = strtolower(pathinfo($inPath, PATHINFO_EXTENSION));
        $c = $this->find($fromExt, $toExt);
        if (! $c) {
            throw new ConversionException("No converter for {$fromExt} → {$toExt}.");
        }

        return $c->convert($inPath, $toExt, $outPath, $options ?? new ConvertOptions);
    }

    /** Extensions (lower-case) that can end up as STL. */
    public function inputFormats(): array
    {
        $all = ['stl'];
        foreach (['3mf', 'obj', 'ply', 'off', 'glb', 'gltf', 'amf', 'step', 'stp', 'iges', 'igs', 'brep'] as $ext) {
            if ($this->find($ext, 'stl')) {
                $all[] = $ext;
            }
        }

        return $all;
    }
}
