<?php

namespace App\Engines\Converter;

use App\Engines\Contracts\FormatConverter;
use App\Engines\DTO\ConvertOptions;
use App\Engines\DTO\ConvertResult;
use App\Engines\Exceptions\ConversionException;
use App\Engines\Mesh\StlFile;
use Illuminate\Support\Facades\Process;

/** STEP / IGES → STL via FreeCAD in console mode (engines/freecad/convert.py). */
final class FreeCadConverter implements FormatConverter
{
    private const FROM = ['step', 'stp', 'iges', 'igs', 'brep'];

    public function __construct(private readonly array $config) {}

    public function name(): string
    {
        return 'freecad';
    }

    public function supports(string $fromExt, string $toExt): bool
    {
        return in_array($fromExt, self::FROM, true) && $toExt === 'stl' && $this->available();
    }

    public function available(): bool
    {
        $bin = $this->config['bin'];

        return is_file($bin) || (bool) trim((string) Process::run(PHP_OS_FAMILY === 'Windows' ? "where {$bin}" : "command -v {$bin}")->output());
    }

    public function convert(string $inPath, string $toExt, string $outPath, ConvertOptions $options): ConvertResult
    {
        $script = base_path('engines/freecad/convert.py');
        $result = Process::timeout($this->config['timeout'])->run([
            $this->config['bin'], $script, '--', $inPath, $outPath, (string) $options->linearDeflection, (string) $options->angularDeflection,
        ]);
        if (! is_file($outPath) || filesize($outPath) < 100) {
            throw new ConversionException('FreeCAD conversion failed: '.mb_substr($result->output().$result->errorOutput(), -800));
        }

        return new ConvertResult($outPath, $this->name(), StlFile::stats($outPath)->triangles);
    }
}
