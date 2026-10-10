<?php

namespace App\Domain\Farm;

use App\Engines\Translate\Translator;
use App\Models\FarmColor;
use App\Models\FarmMaterial;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The routine of keeping about 250 spools in the catalogue, taken off the owner's hands: the hex of a colour is read
 * from its photo, English names are translated in one call, and the whole list can come from a spreadsheet.
 * Used by `php artisan farm:colors-fill`, `farm:import-colors` and the buttons in /admin/farm/materials.
 */
final class ColorCatalog
{
    /** What a colour carries until somebody (or the photo) says what it looks like. */
    public const UNSET_HEX = '#cccccc';

    public const CSV_COLUMNS = ['code', 'material', 'name', 'name_en', 'hex', 'in_stock', 'sort'];

    private const CHECK_NOTE = 'hex z fotky, zkontrolovat';

    public function __construct(private readonly Translator $translator) {}

    public static function hexMissing(FarmColor $color): bool
    {
        return ! $color->hex || strtolower((string) $color->hex) === self::UNSET_HEX;
    }

    /**
     * The catalogue code of a colour made up by an admin: kind, finish, the name's words, and the maker when it is
     * not ours — `PLA+_White`, `PLA_Silk_Sage_Green`, `PETG_Black_Prusament`; a taken code gets a number.
     */
    public static function codeFor(FarmMaterial $kind, string $name, ?string $maker = null): string
    {
        $words = fn (string $s) => implode('_', array_map('ucfirst', preg_split('/[^A-Za-z0-9+]+/', Str::ascii(trim($s)), -1, PREG_SPLIT_NO_EMPTY) ?: []));
        $base = implode('_', array_filter([
            $kind->code, $kind->finish === 'solid' ? '' : ucfirst($kind->finish), $words($name) ?: 'Color',
            trim((string) $maker) && trim((string) $maker) !== FarmMaterial::DEFAULT_MAKER ? $words((string) $maker) : '',
        ]));
        $code = $base;
        for ($n = 2; FarmColor::where('code', $code)->exists(); $n++) {
            $code = $base.'_'.$n;
        }

        return $code;
    }

    /**
     * Fills what is missing: hex from the photo, English names from the Czech ones.
     *
     * @param  list<string>  $only  hex | name_en (empty = both)
     * @return array{hex: list<array{code: string, hex: string, note: bool}>, name_en: list<array{code: string, name: string, name_en: string}>, skipped: list<array{code: string, why: string}>}
     */
    public function fill(array $only = [], bool $dry = false): array
    {
        $report = ['hex' => [], 'name_en' => [], 'skipped' => []];
        $label = fn (FarmColor $c) => (string) ($c->code ?: '#'.$c->id.' '.$c->name);
        if (! $only || in_array('hex', $only, true)) {
            foreach (FarmColor::with('material')->orderBy('sort')->orderBy('id')->get()->filter(fn ($c) => self::hexMissing($c)) as $color) {
                if (! $color->photo_path || ! Storage::disk('public')->exists($color->photo_path)) {
                    $report['skipped'][] = ['code' => $label($color), 'why' => 'no photo to read the hex from'];

                    continue;
                }
                $special = Palette::hue('#808080', (string) ($color->material->finish ?? 'solid'), $color->name.' '.$color->name_en) === 'special';
                $hex = self::hexFromPhoto(Storage::disk('public')->path($color->photo_path), $special);
                if (! $hex) {
                    $report['skipped'][] = ['code' => $label($color), 'why' => 'the photo could not be read'];

                    continue;
                }
                if (! $dry) {
                    $color->hex = $hex;
                    // a rainbow or a wood has no one colour: the dominant one stands in and a person should look at it
                    if ($special && ! str_contains((string) $color->test_notes, self::CHECK_NOTE)) {
                        $color->test_notes = trim($color->test_notes."\n".self::CHECK_NOTE);
                    }
                    $color->save();
                }
                $report['hex'][] = ['code' => $label($color), 'hex' => $hex, 'note' => $special];
            }
        }
        if (! $only || in_array('name_en', $only, true)) {
            $missing = FarmColor::orderBy('sort')->orderBy('id')->get()->filter(fn ($c) => trim((string) $c->name_en) === '' && trim((string) $c->name) !== '')->values();
            if ($missing->isNotEmpty()) {
                if (! $this->translator->available()) {
                    foreach ($missing as $color) {
                        $report['skipped'][] = ['code' => $label($color), 'why' => 'the translator is not set up (name_en)'];
                    }
                } else {
                    // one call for all of them: a numbered list, answered as the same numbered list
                    $text = "Názvy barev filamentů pro 3D tisk. Přelož každý řádek, zachovej číslování a pořadí, nic nepřidávej:\n"
                        .$missing->map(fn ($c, $i) => ($i + 1).'. '.$c->name)->implode("\n");
                    $answer = (string) ($this->translator->translate($text, ['en'], 'cs', Translator::STYLE_FAITHFUL, ['kind' => 'farm_colors'])->texts['en'] ?? '');
                    $names = [];
                    foreach (preg_split('/\R/u', $answer) ?: [] as $line) {
                        if (preg_match('/^\s*(?:\[en\]\s*)?(\d+)\.\s*(.+?)\s*$/u', $line, $m)) {
                            $names[(int) $m[1]] = $m[2];
                        }
                    }
                    foreach ($missing as $i => $color) {
                        $name = mb_substr(mb_strtolower(trim((string) ($names[$i + 1] ?? ''))), 0, 80);
                        if ($name === '') {
                            $report['skipped'][] = ['code' => $label($color), 'why' => 'the translator gave no line for it (name_en)'];

                            continue;
                        }
                        if (! $dry) {
                            $color->update(['name_en' => $name]);
                        }
                        $report['name_en'][] = ['code' => $label($color), 'name' => (string) $color->name, 'name_en' => $name];
                    }
                }
            }
        }

        return $report;
    }

    /**
     * The colour of the printed sample on a photo: the picture is made small, its background is taken away by
     * flooding from the corners, and of what is left in the middle half the median is read (per channel). For a
     * filament without one colour (`dominant`) the most frequent coarse colour is taken instead.
     */
    public static function hexFromPhoto(string $path, bool $dominant = false): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! is_file($path)) {
            return null;
        }
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if (! $src) {
            return null;
        }
        $n = 96;
        $im = imagecreatetruecolor($n, $n);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imagecopyresampled($im, $src, 0, 0, 0, 0, $n, $n, imagesx($src), imagesy($src));
        $px = [];
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                $c = imagecolorat($im, $x, $y);
                $px[$y][$x] = [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
            }
        }
        // background: everything reachable from a corner through pixels close to that corner's colour
        $bg = [];
        $tolerance = 34;
        foreach ([[0, 0], [$n - 1, 0], [0, $n - 1], [$n - 1, $n - 1]] as [$sx, $sy]) {
            $seed = $px[$sy][$sx];
            $queue = [[$sx, $sy]];
            while ($queue) {
                [$x, $y] = array_pop($queue);
                if ($x < 0 || $y < 0 || $x >= $n || $y >= $n || isset($bg[$y][$x])) {
                    continue;
                }
                $p = $px[$y][$x];
                if (abs($p[0] - $seed[0]) + abs($p[1] - $seed[1]) + abs($p[2] - $seed[2]) > $tolerance * 3) {
                    continue;
                }
                $bg[$y][$x] = true;
                array_push($queue, [$x + 1, $y], [$x - 1, $y], [$x, $y + 1], [$x, $y - 1]);
            }
        }
        $kept = [];
        for ($y = (int) ($n * 0.25); $y < $n * 0.75; $y++) {
            for ($x = (int) ($n * 0.25); $x < $n * 0.75; $x++) {
                if (! isset($bg[$y][$x])) {
                    $kept[] = $px[$y][$x];
                }
            }
        }
        // the sample fills the whole picture, or it has the colour of the backdrop: the middle as it is
        if (count($kept) < 60) {
            $kept = [];
            for ($y = (int) ($n * 0.35); $y < $n * 0.65; $y++) {
                for ($x = (int) ($n * 0.35); $x < $n * 0.65; $x++) {
                    $kept[] = $px[$y][$x];
                }
            }
        }
        if ($dominant) {
            $bins = [];
            foreach ($kept as $p) {
                $bins[($p[0] >> 4).'.'.($p[1] >> 4).'.'.($p[2] >> 4)][] = $p;
            }
            usort($bins, fn ($a, $b) => count($b) <=> count($a));
            $kept = $bins[0];
        }
        $median = function (int $channel) use ($kept): int {
            $v = array_column($kept, $channel);
            sort($v);

            return $v[intdiv(count($v), 2)];
        };

        return sprintf('#%02x%02x%02x', $median(0), $median(1), $median(2));
    }

    /**
     * A spreadsheet of spools (CSV, `;` between columns, UTF-8, a header row): creates or updates by `code`.
     * Columns: code; material; name; name_en?; hex?; in_stock?; sort?
     *
     * @return array{created: list<string>, updated: list<array{code: string, changes: array<string, array{0: mixed, 1: mixed}>}>, same: int, errors: list<string>}
     */
    public function import(string $csvPath, bool $dry = false): array
    {
        $report = ['created' => [], 'updated' => [], 'same' => 0, 'errors' => []];
        $raw = @file_get_contents($csvPath);
        if ($raw === false) {
            return ['errors' => ['The file could not be read.']] + $report;
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        if (! mb_check_encoding($raw, 'UTF-8')) {
            return ['errors' => ['The file is not UTF-8: save it as "CSV UTF-8".']] + $report;
        }
        $rows = array_values(array_filter(array_map(fn ($l) => str_getcsv($l, ';', '"', ''), preg_split('/\R/u', $raw) ?: []), fn ($r) => $r !== [null] && implode('', $r) !== ''));
        $head = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows) ?? []);
        foreach (['code', 'material', 'name'] as $need) {
            if (! in_array($need, $head, true)) {
                return ['errors' => ['The header row has to name the columns code;material;name (found: '.implode(';', $head).').']] + $report;
            }
        }
        $materials = FarmMaterial::all();
        foreach ($rows as $i => $cells) {
            $row = [];
            foreach ($head as $c => $name) {
                $row[$name] = trim((string) ($cells[$c] ?? ''));
            }
            $line = $i + 2;
            if ($row['code'] === '' || $row['name'] === '') {
                $report['errors'][] = "line {$line}: code and name are required";

                continue;
            }
            $material = self::material($materials, $row['material']);
            if (! $material) {
                $report['errors'][] = "line {$line}: unknown material \"{$row['material']}\" (known: ".$materials->map(fn ($m) => $m->code.($m->finish === 'solid' ? '' : ' '.$m->finish))->implode(', ').')';

                continue;
            }
            $hex = strtolower($row['hex'] ?? '');
            if ($hex !== '' && ! preg_match('/^#[0-9a-f]{6}$/', $hex)) {
                $report['errors'][] = "line {$line}: hex \"{$row['hex']}\" is not #rrggbb";

                continue;
            }
            $color = FarmColor::where('code', $row['code'])->first();
            $new = ['farm_material_id' => $material->id, 'name' => $row['name']];
            // an empty cell leaves what the catalogue has (or what colors-fill will find out)
            if (($row['name_en'] ?? '') !== '') {
                $new['name_en'] = $row['name_en'];
            }
            if ($hex !== '') {
                $new['hex'] = $hex;
            }
            if (($row['in_stock'] ?? '') !== '') {
                $new['in_stock'] = in_array(strtolower($row['in_stock']), ['1', 'ano', 'yes', 'true', 'y', 'a', 'x', 'si', 'sí'], true);
            }
            if (($row['sort'] ?? '') !== '' && is_numeric($row['sort'])) {
                $new['sort'] = (int) $row['sort'];
            }
            if (! $color) {
                if (! $dry) {
                    FarmColor::create($new + ['code' => $row['code'], 'hex' => self::UNSET_HEX, 'enabled' => true]);
                }
                $report['created'][] = $row['code'];

                continue;
            }
            $changes = [];
            foreach ($new as $key => $value) {
                if ($color->{$key} != $value) {
                    $changes[$key] = [$color->{$key}, $value];
                }
            }
            if (! $changes) {
                $report['same']++;

                continue;
            }
            if (! $dry) {
                $color->update($new);
            }
            $report['updated'][] = ['code' => $row['code'], 'changes' => $changes];
        }

        return $report;
    }

    /** "PLA+", "PLA+ matte", "PLA Silk", "PLA matný": the kind of filament a row of the spreadsheet names. */
    private static function material($materials, string $name): ?FarmMaterial
    {
        $want = Palette::plain($name);
        foreach ($materials as $m) {
            $forms = [$m->code.' '.$m->finish, $m->name.' '.$m->finish, $m->label(), $m->code.' '.__('farm.finish.'.$m->finish, [], 'cs')];
            if ($m->finish === 'solid') {
                array_push($forms, $m->code, $m->name);
            }
            if (in_array($want, array_map(fn ($f) => Palette::plain($f), $forms), true)) {
                return $m;
            }
        }

        return null;
    }
}
