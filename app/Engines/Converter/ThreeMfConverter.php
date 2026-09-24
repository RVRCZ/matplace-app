<?php

namespace App\Engines\Converter;

use App\Engines\Contracts\FormatConverter;
use App\Engines\DTO\ConvertOptions;
use App\Engines\DTO\ConvertResult;
use App\Engines\Exceptions\ConversionException;
use App\Engines\Mesh\StlFile;

/**
 * 3MF → binary STL in world space. Pure PHP, ported from the legacy SlicerService.
 * Handles inline meshes and production-extension components (3D/Objects/*.model) with build and component transforms.
 * Positional XML parsing on purpose: huge meshes would hit PCRE backtrack limits with full-document regexes.
 */
final class ThreeMfConverter implements FormatConverter
{
    public function name(): string
    {
        return 'threemf';
    }

    public function supports(string $fromExt, string $toExt): bool
    {
        return $fromExt === '3mf' && $toExt === 'stl';
    }

    public function convert(string $inPath, string $toExt, string $outPath, ConvertOptions $options): ConvertResult
    {
        $zip = new \ZipArchive;
        if ($zip->open($inPath) !== true) {
            throw new ConversionException('Not a valid 3MF (zip) file.');
        }
        $main = $zip->getFromName('3D/3dmodel.model');
        if ($main === false) {
            $zip->close();
            throw new ConversionException('3MF has no 3D/3dmodel.model.');
        }

        $oldMem = ini_get('memory_limit');
        ini_set('memory_limit', '2048M');
        $fh = StlFile::beginBinary($outPath);
        $cache = [];
        $count = 0;
        try {
            preg_match_all('#<item\b[^>]*>#', $main, $items);
            foreach ($items[0] as $it) {
                if (! preg_match('/\bobjectid="(\d+)"/', $it, $oi)) {
                    continue;
                }
                preg_match('/\btransform="([^"]*)"/', $it, $tr);
                $this->emit($zip, '3D/3dmodel.model', $oi[1], $this->parseTransform($tr[1] ?? null), $cache, $fh, $count);
            }
        } finally {
            $zip->close();
            StlFile::endBinary($fh, $count);
            // PHP refuses a limit below what is already allocated (a big 3MF leaves the worker at 256 MB+): then keep the raised one
            @ini_set('memory_limit', $oldMem);
        }

        if ($count === 0) {
            @unlink($outPath);
            throw new ConversionException('3MF contains no triangles.');
        }

        return new ConvertResult($outPath, $this->name(), $count);
    }

    /** 3MF transform (12 numbers, row-major 3x4) → array or null. */
    private function parseTransform(?string $s): ?array
    {
        if ($s === null || trim($s) === '') {
            return null;
        }
        $p = preg_split('/\s+/', trim($s));

        return count($p) >= 12 ? array_map('floatval', array_slice($p, 0, 12)) : null;
    }

    /** Compose two 3x4 transforms (first $a, then $b). */
    private function mul(?array $a, ?array $b): ?array
    {
        if (! $a) {
            return $b;
        }
        if (! $b) {
            return $a;
        }
        $r = array_fill(0, 12, 0.0);
        for ($i = 0; $i < 3; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $s = 0.0;
                for ($k = 0; $k < 3; $k++) {
                    $s += $a[$i * 3 + $k] * $b[$k * 3 + $j];
                }
                $r[$i * 3 + $j] = $s;
            }
        }
        for ($j = 0; $j < 3; $j++) {
            $s = 0.0;
            for ($k = 0; $k < 3; $k++) {
                $s += $a[9 + $k] * $b[$k * 3 + $j];
            }
            $r[9 + $j] = $s + $b[9 + $j];
        }

        return $r;
    }

    private function loadObjects(\ZipArchive $zip, string $path, array &$cache): array
    {
        $path = ltrim($path, '/');
        if (isset($cache[$path])) {
            return $cache[$path];
        }
        $xml = $zip->getFromName($path);
        if ($xml === false) {
            return $cache[$path] = [];
        }
        $objs = [];
        if (! preg_match_all('#<object\b[^>]*>#', $xml, $op, PREG_OFFSET_CAPTURE)) {
            return $cache[$path] = [];
        }
        foreach ($op[0] as $m) {
            $bodyStart = $m[1] + strlen($m[0]);
            $e = strpos($xml, '</object>', $bodyStart);
            if ($e === false) {
                $e = strlen($xml);
            }
            if (! preg_match('/\bid="(\d+)"/', $m[0], $im)) {
                continue;
            }
            $id = $im[1];
            $body = substr($xml, $bodyStart, $e - $bodyStart);
            if (str_contains($body, '<vertex')) {
                preg_match_all('/<vertex x="([-\d.eE+]+)" y="([-\d.eE+]+)" z="([-\d.eE+]+)"/', $body, $vv);
                preg_match_all('/<triangle v1="(\d+)" v2="(\d+)" v3="(\d+)"/', $body, $tt);
                $objs[$id] = ['v' => [$vv[1], $vv[2], $vv[3]], 't' => [$tt[1], $tt[2], $tt[3]]];
            } elseif (str_contains($body, '<component')) {
                preg_match_all('#<component\b[^>]*>#', $body, $cc);
                $comps = [];
                foreach ($cc[0] as $c) {
                    preg_match('/\bobjectid="(\d+)"/', $c, $oi);
                    preg_match('/\bp:path="([^"]*)"/', $c, $pp);
                    preg_match('/\btransform="([^"]*)"/', $c, $tr);
                    $comps[] = ['oid' => $oi[1] ?? null, 'path' => $pp[1] ?? $path, 'tf' => $this->parseTransform($tr[1] ?? null)];
                }
                $objs[$id] = ['c' => $comps];
            }
        }

        return $cache[$path] = $objs;
    }

    private function emit(\ZipArchive $zip, string $path, ?string $oid, ?array $tf, array &$cache, $fh, int &$count): void
    {
        if ($oid === null) {
            return;
        }
        $objs = $this->loadObjects($zip, $path, $cache);
        if (! isset($objs[$oid])) {
            return;
        }
        $o = $objs[$oid];
        if (isset($o['v'])) {
            [$xs, $ys, $zs] = $o['v'];
            $n = count($xs);
            $V = [];
            for ($i = 0; $i < $n; $i++) {
                $x = (float) $xs[$i];
                $y = (float) $ys[$i];
                $z = (float) $zs[$i];
                $V[$i] = $tf
                    ? [$x * $tf[0] + $y * $tf[3] + $z * $tf[6] + $tf[9], $x * $tf[1] + $y * $tf[4] + $z * $tf[7] + $tf[10], $x * $tf[2] + $y * $tf[5] + $z * $tf[8] + $tf[11]]
                    : [$x, $y, $z];
            }
            [$a, $b, $c] = $o['t'];
            $m = count($a);
            for ($i = 0; $i < $m; $i++) {
                $p = $V[(int) $a[$i]] ?? null;
                $q = $V[(int) $b[$i]] ?? null;
                $r = $V[(int) $c[$i]] ?? null;
                if ($p && $q && $r) {
                    StlFile::writeTriangle($fh, $p, $q, $r);
                    $count++;
                }
            }
        } elseif (isset($o['c'])) {
            foreach ($o['c'] as $cp) {
                $this->emit($zip, $cp['path'], $cp['oid'], $this->mul($cp['tf'], $tf), $cache, $fh, $count);
            }
        }
    }
}
