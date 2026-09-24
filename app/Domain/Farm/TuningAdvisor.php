<?php

namespace App\Domain\Farm;

use App\Models\FarmPrinterMaterial;

/**
 * What the operator saw on a test object → what to change. Rules of thumb every print farm uses, written down once:
 * stringing wants a cooler nozzle and more retraction, sagging overhangs want more fan, a bulging corner wants a
 * slower outer wall and pressure advance, a small cube wants contour compensation… The advice is a list of
 * proposed settings with a reason each; the operator decides, and the next test print shows whether it helped.
 *
 * Result keys (from the evaluation form; missing = not judged):
 *   stringing 0-3 · overhang_ok 0|30|40|50|60|70 (steepest clean angle) · bridge ok|sag|fail · elephant 0-2 ·
 *   corners ok|bulge|round|gaps · top ok|pillow|gaps · wall ok|gaps|missing · bond ok|weak · warp ok|lift ·
 *   ironing ok|lines|bumps|rough ·
 *   cube_x cube_y cube_z hole (measured mm) · best_floor (tower)
 */
final class TuningAdvisor
{
    /** What the shipped profiles say when the candidate does not: the base a delta is applied to. */
    private const BASE = [
        'filament' => ['fan_max_speed' => 100, 'fan_min_speed' => 100, 'overhang_fan_speed' => 100, 'filament_retraction_length' => 0.8, 'filament_flow_ratio' => 0.98, 'pressure_advance' => 0.035, 'slow_down_layer_time' => 8, 'filament_max_volumetric_speed' => 12],
        'process' => ['bridge_speed' => 50, 'outer_wall_speed' => 200, 'top_shell_layers' => 5, 'elefant_foot_compensation' => 0.1, 'xy_contour_compensation' => 0, 'xy_hole_compensation' => 0, 'brim_width' => 5, 'outer_wall_acceleration' => 5000, 'ironing_flow' => '10%', 'ironing_speed' => 30, 'ironing_spacing' => 0.15],
    ];

    public const CUBE_MM = 15.0;

    public const HOLE_MM = 8.0;

    /**
     * @param  array  $candidate  what the test was printed with (FarmPrinterMaterial overrides shape, temperatures filled)
     * @param  array<int, int>|null  $towerTemps  floor temperatures of a tower, bottom first
     * @return array{overrides: array, advice: array<int, array{setting: string, from: mixed, to: mixed, reason: string}>, notes: array<int, string>}
     */
    public static function advise(array $result, array $candidate, string $object, ?array $towerTemps = null): array
    {
        $a = new self($candidate);
        $r = fn (string $k) => $result[$k] ?? null;

        if ($object === 'temp_tower' && $towerTemps && ($r('best_floor') !== null)) {
            $floor = max(1, min(count($towerTemps), (int) $r('best_floor')));
            $a->setTemp('nozzle_temp', $towerTemps[$floor - 1], 'patro '.$floor.' vypadalo nejlépe');
        }

        $stringing = (int) $r('stringing');
        if ($stringing >= 2) {
            $a->temp('nozzle_temp', $stringing >= 3 ? -10 : -5, 'stringing '.$stringing.'/3: chladnější tryska méně teče');
            $a->filament('filament_retraction_length', $stringing >= 3 ? 0.4 : 0.2, 0.2, 2.5, 'stringing: delší retrakce');
            $a->note('Silný stringing bývá i mokrý filament: před další změnou nastavení cívku vysušte.');
        } elseif ($stringing === 1) {
            $a->filament('filament_retraction_length', 0.1, 0.2, 2.5, 'lehký stringing: o kousek delší retrakce');
        }

        $overhang = $r('overhang_ok');
        if ($overhang !== null && (int) $overhang < 50) {
            $a->filament('fan_max_speed', 15, 0, 100, 'převis drží jen do '.(int) $overhang.'°: více chlazení');
            $a->filament('overhang_fan_speed', 100, 0, 100, 'převisy na plný ventilátor', absolute: true);
            if ((int) $overhang < 40 && $stringing < 2) {
                $a->temp('nozzle_temp', -5, 'převisy pod 40°: chladnější tryska tuhne rychleji');
            }
        }

        $bridge = $r('bridge');
        if ($bridge === 'sag' || $bridge === 'fail') {
            $a->process('bridge_speed', $bridge === 'fail' ? -20 : -10, 15, 200, 'most '.($bridge === 'fail' ? 'spadl' : 'prověšený').': pomalejší mosty');
            $a->filament('overhang_fan_speed', 100, 0, 100, 'most: plný ventilátor nad vzduchem', absolute: true);
        }

        $elephant = (int) $r('elephant');
        if ($elephant >= 1) {
            $a->process('elefant_foot_compensation', $elephant >= 2 ? 0.2 : 0.1, 0, 0.5, 'sloní noha: větší kompenzace první vrstvy');
            if ($elephant >= 2) {
                $a->temp('bed_temp', -5, 'silná sloní noha: chladnější podložka');
            }
        }

        $corners = $r('corners');
        if ($corners === 'bulge') {
            $a->process('outer_wall_speed', -20, 30, 300, 'vyboulené rohy: pomalejší vnější stěna', percent: true);
            $a->filament('pressure_advance', 0.01, 0, 0.2, 'vyboulené rohy / blobky: vyšší pressure advance');
        } elseif ($corners === 'round') {
            // the nozzle sweeps through the corner: less speed and acceleration on the outer wall, more pressure advance
            $a->process('outer_wall_speed', -20, 30, 300, 'zaoblené rohy: pomalejší vnější stěna', percent: true);
            $a->process('outer_wall_acceleration', -40, 500, 20000, 'zaoblené rohy: nižší akcelerace vnější stěny', percent: true);
            $a->filament('pressure_advance', 0.01, 0, 0.2, 'zaoblené rohy: vyšší pressure advance');
        } elseif ($corners === 'gaps') {
            $a->filament('filament_flow_ratio', 0.02, 0.85, 1.15, 'mezery ve stěnách: vyšší průtok');
        }

        $top = $r('top');
        if ($top === 'pillow') {
            $a->process('top_shell_layers', 1, 3, 8, 'zvlněný vršek: víc vrchních vrstev');
            $a->filament('fan_max_speed', 10, 0, 100, 'zvlněný vršek: více chlazení');
        } elseif ($top === 'gaps') {
            $a->filament('filament_flow_ratio', 0.02, 0.85, 1.15, 'děravý vršek: vyšší průtok');
            $a->process('top_shell_layers', 1, 3, 8, 'děravý vršek: víc vrchních vrstev');
        }

        if ($r('wall') === 'gaps' || $r('wall') === 'missing') {
            $a->filament('filament_flow_ratio', 0.02, 0.85, 1.15, 'tenká stěna děravá: vyšší průtok');
        }

        if ($r('bond') === 'weak') {
            $a->temp('nozzle_temp', 5, 'slabé spojení vrstev: teplejší tryska');
            $a->filament('fan_max_speed', -15, 0, 100, 'slabé spojení vrstev: méně chlazení');
            $a->filament('filament_max_volumetric_speed', -20, 2, 40, 'slabé spojení vrstev: pomalejší tavení, plast se lépe prohřeje', percent: true);
        }

        $ironing = $r('ironing');
        if ($ironing === 'lines') {
            $a->process('ironing_spacing', -0.05, 0.05, 0.5, 'ironing: viditelné čáry, hustší tahy');
            $a->process('ironing_flow', 2, 0, 40, 'ironing: viditelné čáry, o trochu více materiálu');
        } elseif ($ironing === 'bumps') {
            $a->process('ironing_flow', -3, 0, 40, 'ironing: hrbolky a přebytek, méně materiálu');
        } elseif ($ironing === 'rough') {
            $a->process('ironing_speed', -10, 10, 100, 'ironing: hrubý povrch, pomalejší žehlení', percent: true);
            $a->temp('nozzle_temp', 5, 'ironing: hrubý povrch, teplejší tryska plast lépe uhladí');
        }

        if ($r('warp') === 'lift') {
            $a->temp('bed_temp', 5, 'odlepené rohy: teplejší podložka');
            $a->process('brim_type', 'outer_only', null, null, 'odlepené rohy: límec', absolute: true);
        }

        $xs = array_filter([(float) ($r('cube_x') ?? 0), (float) ($r('cube_y') ?? 0)]);
        if ($xs) {
            $dev = round(array_sum($xs) / count($xs) - self::CUBE_MM, 3);   // negative = printed too small
            if (abs($dev) >= 0.08) {
                $a->process('xy_contour_compensation', round(-$dev / 2, 3), -0.5, 0.5, sprintf('kostka %+.2f mm: kompenzace obrysu', $dev));
            }
        }
        if ($r('hole')) {
            $dev = round((float) $r('hole') - self::HOLE_MM, 3);
            if (abs($dev) >= 0.08) {
                $a->process('xy_hole_compensation', round(-$dev / 2, 3), -0.5, 0.5, sprintf('otvor %+.2f mm: kompenzace otvorů', $dev));
            }
        }
        if ($r('cube_z')) {
            $dev = round((float) $r('cube_z') - self::CUBE_MM, 3);
            if ($dev <= -0.15) {
                $a->note(sprintf('Kostka je o %.2f mm nižší: první vrstva je moc přimáčknutá (Z-offset tiskárny).', -$dev));
            } elseif ($dev >= 0.15) {
                $a->note(sprintf('Kostka je o %.2f mm vyšší: první vrstva je moc vysoko (Z-offset tiskárny).', $dev));
            }
        }

        return $a->result();
    }

    // ── accumulating the proposal ────────────────────────────────────────────

    private array $overrides;

    private array $advice = [];

    private array $notes = [];

    private function __construct(array $candidate)
    {
        $this->overrides = FarmPrinterMaterial::clean($candidate);
    }

    private function temp(string $key, int $delta, string $reason): void
    {
        $from = (int) ($this->overrides[$key] ?? 0);
        if (! $from) {
            $this->notes[] = $reason.' (teplota není známa, nastavte ji ručně)';

            return;
        }
        $this->setTemp($key, $from + $delta, $reason);
    }

    private function setTemp(string $key, int $to, string $reason): void
    {
        $from = (int) ($this->overrides[$key] ?? 0);
        $to = max($key === 'bed_temp' ? 0 : 150, min($key === 'bed_temp' ? 120 : 320, $to));
        if ($to === $from) {
            return;
        }
        $this->overrides[$key] = $to;
        if ($key === 'nozzle_temp') {
            $this->overrides['nozzle_temp_first'] = $to + 5;
        }
        $this->advice[] = ['setting' => $key, 'from' => $from ?: null, 'to' => $to, 'reason' => $reason];
    }

    private function filament(string $key, float|int $delta, ?float $min, ?float $max, string $reason, bool $absolute = false, bool $percent = false): void
    {
        $this->change('filament', $key, $delta, $min, $max, $reason, $absolute, $percent);
    }

    private function process(string $key, float|int|string $delta, ?float $min, ?float $max, string $reason, bool $absolute = false, bool $percent = false): void
    {
        $this->change('process', $key, $delta, $min, $max, $reason, $absolute, $percent);
    }

    private function change(string $group, string $key, float|int|string $delta, ?float $min, ?float $max, string $reason, bool $absolute, bool $percent = false): void
    {
        $raw = $this->overrides[$group][$key] ?? self::BASE[$group][$key] ?? null;
        $from = is_array($raw) ? ($raw[0] ?? null) : $raw;
        $unit = is_string($from) && str_ends_with($from, '%') ? '%' : '';   // ironing_flow is "10%"
        if (is_string($delta)) {
            $to = $delta;
        } else {
            $from = $unit ? rtrim((string) $from, '%') : $from;
            $base = is_numeric($from) ? (float) $from : 0.0;
            $to = $absolute ? (float) $delta : ($percent ? $base * (1 + $delta / 100) : $base + $delta);
            if ($min !== null) {
                $to = max($min, $to);
            }
            if ($max !== null) {
                $to = min($max, $to);
            }
            $to = round($to, 3);
            if (is_numeric($from) && abs($to - (float) $from) < 0.0005) {
                return;
            }
            $to = (fmod($to, 1.0) === 0.0 ? (int) $to : $to).$unit;
            $from = $from === null ? null : $from.$unit;
        }
        $this->overrides[$group][$key] = $group === 'filament' ? [$to] : $to;
        $this->advice[] = ['setting' => $group.'.'.$key, 'from' => $from, 'to' => $to, 'reason' => $reason];
    }

    private function note(string $text): void
    {
        $this->notes[] = $text;
    }

    private function result(): array
    {
        return ['overrides' => $this->overrides, 'advice' => $this->advice, 'notes' => array_values(array_unique($this->notes))];
    }
}
