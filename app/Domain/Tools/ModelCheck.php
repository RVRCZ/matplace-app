<?php

namespace App\Domain\Tools;

use App\Models\ModelFile;

/**
 * Pre-print check of an uploaded model: only things the mesh libraries find reliably (size, closedness, orientation of
 * faces, separate bodies, weight of the file). Two levels on purpose:
 *
 *   error  — a slicer will most likely produce something else than the customer expects
 *   advice — prints, but it is worth knowing
 *
 * It never says "printable": overhangs, wall strength or tolerances depend on the printer, material and orientation.
 */
final class ModelCheck
{
    /** @return array{status: string, items: array<int, array{level: string, code: string, params: array<string, string>}>} */
    public static function report(ModelFile $file): array
    {
        $r = (array) $file->mesh_report;
        $bbox = (array) ($file->bbox ?? $r['bbox'] ?? []);
        if (! $file->isReady() || ! $bbox) {
            return ['status' => 'pending', 'items' => []];
        }
        $dims = [(float) ($bbox['x'] ?? 0), (float) ($bbox['y'] ?? 0), (float) ($bbox['z'] ?? 0)];
        $max = max($dims);
        $min = min($dims);
        $bed = (array) config('pricing.bed_mm', ['x' => 250, 'y' => 250, 'z' => 250]);
        $sorted = $dims;
        rsort($sorted);
        $bedSorted = [(float) $bed['x'], (float) $bed['y'], (float) $bed['z']];
        rsort($bedSorted);
        $size = implode(' × ', array_map(fn ($v) => rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.'), $dims)).' mm';
        $items = [];
        $add = function (string $level, string $code, array $params = []) use (&$items) {
            $items[] = ['level' => $level, 'code' => $code, 'params' => array_map('strval', $params)];
        };
        $intended = ! empty($file->tool_params['stand']) || in_array($file->kind(), ['box', 'vase', 'stamp', 'qr', 'logo', 'lightbox'], true);   // our own multi-part products are several bodies by design

        // ── size: the most common real problem is the wrong unit ───────────────
        if ($max < 1.0) {
            $add('error', 'units_tiny', ['size' => $size]);
        } elseif ($max > 2000) {
            $add('error', 'units_huge', ['size' => $size]);
        } elseif ($max < 5.0) {
            $add('advice', 'very_small', ['size' => $size]);
        } elseif ($sorted[0] > $bedSorted[0] || $sorted[1] > $bedSorted[1] || $sorted[2] > $bedSorted[2]) {
            $add('advice', 'exceeds_bed', ['size' => $size, 'bed' => (int) $bed['x'].' × '.(int) $bed['y'].' × '.(int) $bed['z'].' mm']);
        } else {
            $add('ok', 'size_ok', ['size' => $size]);
        }
        if ($max >= 1.0 && $min < 0.8) {
            $add('error', 'too_thin', ['t' => rtrim(rtrim(number_format($min, 2, '.', ''), '0'), '.')]);
        }

        // ── mesh ────────────────────────────────────────────────────────────────
        if (array_key_exists('watertight', $r)) {
            $r['watertight'] ? $add('ok', 'watertight_ok') : $add('error', 'not_watertight');
        }
        if (! empty($r['flipped_normals'])) {
            $add('error', 'flipped_normals');
        }
        $shells = (int) ($r['shells'] ?? 1);
        if ($shells > 1 && ! $intended) {
            $add('advice', 'multiple_shells', ['n' => $shells]);
        }
        $tri = (int) ($file->triangles ?? $r['triangles'] ?? 0);
        if ($tri > 1500000) {
            $add('advice', 'heavy_mesh', ['n' => number_format($tri / 1e6, 1, '.', '')]);
        } elseif ($tri > 0 && $tri < 40 && $shells <= 1 && $max > 20) {
            $add('advice', 'very_coarse', ['n' => $tri]);
        }

        $levels = array_column($items, 'level');

        return ['status' => in_array('error', $levels, true) ? 'error' : (in_array('advice', $levels, true) ? 'advice' : 'ok'), 'items' => $items];
    }
}
