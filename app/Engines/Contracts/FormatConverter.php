<?php

namespace App\Engines\Contracts;

use App\Engines\DTO\ConvertOptions;
use App\Engines\DTO\ConvertResult;

/** Format conversion (e.g. STEP → STL). Extensions are lower-case without a dot. */
interface FormatConverter
{
    public function supports(string $fromExt, string $toExt): bool;

    /** @throws \App\Engines\Exceptions\ConversionException */
    public function convert(string $inPath, string $toExt, string $outPath, ConvertOptions $options): ConvertResult;

    public function name(): string;
}
