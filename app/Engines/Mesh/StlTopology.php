<?php

namespace App\Engines\Mesh;

/**
 * Closed-surface test in plain PHP: in a printable solid every edge belongs to exactly two triangles.
 * An edge used once is the rim of a hole, an edge used three or more times is a non-manifold joint.
 * Used where Python is not available (and by the tests); large meshes are left to trimesh.
 */
final class StlTopology
{
    public const MAX_TRIANGLES = 300000;   // above this the edge table would not fit a PHP worker comfortably

    /** @return array{checked: bool, triangles: int, open_edges: int, non_manifold_edges: int, watertight: bool|null} */
    public static function check(string $path): array
    {
        $ids = [];
        $edges = [];
        $count = 0;
        foreach (StlFile::triangles($path) as $tri) {
            if (++$count > self::MAX_TRIANGLES) {
                return ['checked' => false, 'triangles' => $count, 'open_edges' => 0, 'non_manifold_edges' => 0, 'watertight' => null];
            }
            $v = [];
            foreach ($tri as $p) {
                // STL repeats coordinates per triangle; equal floats mean the same vertex (1 µm grid absorbs export noise)
                $key = round($p[0], 3).' '.round($p[1], 3).' '.round($p[2], 3);
                $v[] = $ids[$key] ??= count($ids);
            }
            if ($v[0] === $v[1] || $v[1] === $v[2] || $v[0] === $v[2]) {
                continue;   // degenerate sliver: carries no surface
            }
            foreach ([[0, 1], [1, 2], [2, 0]] as [$i, $j]) {
                $e = $v[$i] < $v[$j] ? $v[$i].'-'.$v[$j] : $v[$j].'-'.$v[$i];
                $edges[$e] = ($edges[$e] ?? 0) + 1;
            }
        }
        $open = $bad = 0;
        foreach ($edges as $n) {
            if ($n === 1) {
                $open++;
            } elseif ($n > 2) {
                $bad++;
            }
        }

        return ['checked' => true, 'triangles' => $count, 'open_edges' => $open, 'non_manifold_edges' => $bad, 'watertight' => $count > 0 && $open === 0 && $bad === 0];
    }
}
