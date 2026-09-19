<?php

namespace App\Engines\Converter;

use App\Engines\Contracts\FormatConverter;
use App\Engines\DTO\ConvertOptions;
use App\Engines\DTO\ConvertResult;
use App\Engines\Exceptions\ConversionException;
use App\Engines\Mesh\StlFile;
use App\Engines\Repair\PythonTool;

/** OBJ / PLY / OFF / GLB / GLTF → STL via trimesh (engines/python/mesh_tool.py). */
final class PythonMeshConverter implements FormatConverter
{
    private const FROM = ['obj', 'ply', 'off', 'glb', 'gltf', 'amf'];

    public function __construct(private readonly PythonTool $python) {}

    public function name(): string
    {
        return 'trimesh';
    }

    public function supports(string $fromExt, string $toExt): bool
    {
        return in_array($fromExt, self::FROM, true) && $toExt === 'stl' && $this->python->available();
    }

    public function convert(string $inPath, string $toExt, string $outPath, ConvertOptions $options): ConvertResult
    {
        $out = $this->python->run(['convert', $inPath, $outPath]);
        if (empty($out['ok']) || ! is_file($outPath)) {
            throw new ConversionException('trimesh conversion failed: '.($out['error'] ?? 'unknown'));
        }

        return new ConvertResult($outPath, $this->name(), StlFile::stats($outPath)->triangles);
    }
}
