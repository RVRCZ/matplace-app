<?php

namespace App\Engines\Converter;

use App\Engines\Contracts\FormatConverter;
use App\Engines\DTO\ConvertOptions;
use App\Engines\DTO\ConvertResult;
use App\Engines\Exceptions\ConversionException;
use App\Engines\Mesh\StlFile;
use App\Engines\Repair\PythonTool;

/**
 * STEP / IGES / BREP → STL through OpenCascade (cadquery in the Python venv).
 * Same geometry kernel as FreeCAD, but headless and pip-installable; FreeCadConverter stays as an alternative.
 */
final class OcpCadConverter implements FormatConverter
{
    private const FROM = ['step', 'stp', 'iges', 'igs', 'brep'];

    public function __construct(private readonly PythonTool $python) {}

    public function name(): string
    {
        return 'ocp';
    }

    public function supports(string $fromExt, string $toExt): bool
    {
        return in_array($fromExt, self::FROM, true) && $toExt === 'stl' && $this->python->hasCad();
    }

    public function convert(string $inPath, string $toExt, string $outPath, ConvertOptions $options): ConvertResult
    {
        $out = $this->python->run(['cad', $inPath, $outPath, (string) $options->linearDeflection, (string) $options->angularDeflection]);
        if (empty($out['ok']) || ! is_file($outPath)) {
            throw new ConversionException('CAD conversion failed: '.($out['error'] ?? 'unknown'));
        }

        return new ConvertResult($outPath, $this->name(), StlFile::stats($outPath)->triangles);
    }
}
