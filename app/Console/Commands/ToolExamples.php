<?php

namespace App\Console\Commands;

use App\Domain\Farm\Palette;
use App\Domain\Tools\ArtGenerator;
use App\Domain\Tools\ModelEditor;
use App\Domain\Tools\ParametricGenerator;
use App\Engines\Repair\PythonTool;
use App\Support\ToolSeo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Draws the examples shown under a tool's page: for every example in config/tools.php (`seo.examples`) the tool
 * itself builds the model from those parameters and engines/python/render_tool.py draws it. The pictures are kept in
 * the repository (public/img/tool-examples/<tool>-<n>.png); run this again after a tool's geometry or an example changes.
 *
 *   php artisan matplace:tool-examples            every tool, only pictures that are missing
 *   php artisan matplace:tool-examples vase box   just these tools
 *   php artisan matplace:tool-examples --force    draw again what already exists
 *   php artisan matplace:tool-examples --card     the picture of each tool's card instead (public/img/tools/<tool>-800.jpg,
 *                                                 -800.webp, -480.webp): the first example, or `card` of config/tools.php,
 *                                                 3:2 on the beige backdrop, parts in filament colours. A photo of a real
 *                                                 print put at the same path wins: without --force it is never overwritten.
 */
class ToolExamples extends Command
{
    protected $signature = 'matplace:tool-examples {tools?* : tool keys (default: all)} {--force : draw again what already exists} {--card : draw the cards of the tools (public/img/tools) instead of the examples}';

    protected $description = 'Draw the example pictures of the tools (public/img/tool-examples)';

    public function handle(ParametricGenerator $generator, PythonTool $python): int
    {
        if (! $generator->available()) {
            $this->error('The model generator needs Python with trimesh and manifold3d (engines/python).');

            return self::FAILURE;
        }
        $only = (array) $this->argument('tools');
        if ($this->option('card')) {
            return $this->cards($generator, $python, $only);
        }
        $drawn = $kept = $failed = 0;
        foreach ((array) config('tools') as $tool => $definition) {
            $examples = (array) ($definition['seo']['examples'] ?? []);
            if (! $examples || ($only && ! in_array($tool, $only, true)) || (! isset(ParametricGenerator::FIELDS[$tool]) && $tool !== ArtGenerator::KIND)) {
                continue;
            }
            foreach ($examples as $i => $example) {
                $target = ToolSeo::examplePath($tool, $i);
                if (is_file($target) && ! $this->option('force')) {
                    $kept++;

                    continue;
                }
                try {
                    // "use": the product as it is used (a box with its lid on, a vase on its saucer), not laid out for printing
                    $built = $tool === ArtGenerator::KIND ? app(ArtGenerator::class)->build((array) ($example['params'] ?? []), 'use', true)
                        : $generator->build($tool, ToolSeo::exampleParams($tool, $example), 'all', 'use', isset(ParametricGenerator::FAMILY[$tool]));
                } catch (\Throwable $e) {
                    $this->warn(sprintf('%s #%d: the tool refused the parameters (%s)', $tool, $i + 1, mb_substr($e->getMessage(), 0, 160)));
                    $failed++;

                    continue;
                }
                File::ensureDirectoryExists(dirname($target));
                // a picture in colours is drawn in its filaments: the tool says which part is which and what it looks like
                $own = (array) ($built['meta']['notes']['paint'] ?? []);
                $paintFile = null;
                if ($own && ! empty($built['meta']['parts'])) {
                    $paintFile = $built['path'].'.json';
                    File::put($paintFile, json_encode(['color' => $own['body'] ?? '#888888', 'regions' => [],
                        'parts' => array_map(fn ($p) => ['tris' => $p['tris'], 'color' => $own[$p['name']] ?? '#888888'], (array) $built['meta']['parts'])]));
                }
                $result = $python->runScript('render_tool.py', [$built['path'], $target, '800', '600', ...($paintFile ? [$paintFile] : [])], 180);
                @unlink($built['path']);
                $paintFile && @unlink($paintFile);
                if (empty($result['ok']) || ! is_file($target)) {
                    $this->warn(sprintf('%s #%d: not drawn (%s)', $tool, $i + 1, (string) ($result['error'] ?? 'no answer')));
                    $failed++;

                    continue;
                }
                $this->line(sprintf('%-14s #%d  %s  %d kB', $tool, $i + 1, basename($target), (int) ceil(filesize($target) / 1024)));
                $drawn++;
            }
        }
        $this->info("Drawn {$drawn}, kept {$kept}".($failed ? ", failed {$failed}" : '').'.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The card of a file tool is its own output on a model of ours: the generator builds the model, edit_tool.py cuts it
     * (`card` => ['edit' => 'split', 'kind' => 'vase', …, 'edit_params' => […]]). The pieces come back painted by number.
     *
     * @return array{path: string, meta: array<string, mixed>}
     */
    private function edited(ParametricGenerator $generator, PythonTool $python, string $kind, array $card): array
    {
        $source = $generator->build($kind, ToolSeo::exampleParams($kind, $card), 'all', 'print');
        $op = (string) $card['edit'];
        $dir = storage_path('app/tmp/param');
        $out = $dir.'/'.Str::uuid().'.stl';
        $json = $out.'.json';
        $extra = array_intersect_key((array) ($card['edit_params'] ?? []), ['view' => 1]);     // 'cut': the hollow drawn with a quarter taken out
        File::put($json, (string) json_encode(ModelEditor::forTool($op, ModelEditor::clean($op, (array) ($card['edit_params'] ?? []))) + $extra));
        try {
            $r = $python->runScript('edit_tool.py', [$op, $source['path'], $out, '@'.$json], 600);
        } finally {
            @unlink($json);
            @unlink($source['path']);
            @unlink($out.'.stage');
        }
        if (empty($r['ok']) || ! is_file($out)) {
            throw new \RuntimeException((string) ($r['error'] ?? 'edit failed'));
        }
        $tints = ['#5B7FB5', '#C98F5A', '#5A9A6C', '#8E6FB0', '#C45A5A', '#B8A14A', '#4FA3A8', '#A86A8E'];
        $paint = [];
        foreach ((array) ($r['parts'] ?? []) as $part) {
            $paint[$part['name']] = str_starts_with($part['name'], 'piece_') ? $tints[((int) substr($part['name'], 6) - 1) % count($tints)] : '#8C9199';
        }

        return ['path' => $out, 'meta' => ['bbox' => $r['bbox'], 'volume_mm3' => $r['volume_mm3'], 'area_mm2' => $r['area_mm2'], 'triangles' => $r['triangles'], 'notes' => ['paint' => $paint] + (array) ($r['notes'] ?? []), 'parts' => $r['parts'] ?? []]];
    }

    /** The colours the parts of a card are drawn in when the design names none: filaments that read well on the beige. */
    private const CARD_COLORS = ['body' => 'blue', 'lid' => 'orange', 'saucer' => 'grey', 'handle' => 'black', 'stand' => 'black', 'face' => 'black', 'diffuser' => 'white', 'back' => 'grey', 'plate' => 'white', 'text' => 'orange', 'stamp' => 'orange', 'tray' => 'grey'];

    /**
     * Cards: the output of the tool itself. A tool that is one of the generators draws its own first example; a tool
     * that is not (`card` => ['kind' => …, 'params' => …] in config/tools.php) borrows a generator's output that shows
     * what it is about. Tools with neither keep the picture they have.
     */
    private function cards(ParametricGenerator $generator, PythonTool $python, array $only): int
    {
        $palette = app(Palette::class);
        $hex = fn (?string $color) => Palette::BUILT_IN[$color ?? ''] ?? $palette->hex($color) ?? Palette::BUILT_IN['blue'];
        $drawn = $kept = $failed = 0;
        foreach ((array) config('tools') as $tool => $definition) {
            if (($only && ! in_array($tool, $only, true)) || empty($definition['available'])) {
                continue;
            }
            $card = (array) ($definition['card'] ?? []);
            $kind = (string) ($card['kind'] ?? $tool);
            $example = $card ?: ((array) ($definition['seo']['examples'] ?? []))[0] ?? null;
            if ((! isset(ParametricGenerator::FIELDS[$kind]) && $kind !== ArtGenerator::KIND) || $example === null) {
                continue;
            }
            $base = public_path('img/tools/'.$tool);
            if (is_file($base.'-800.jpg') && ! $this->option('force')) {
                $kept++;

                continue;
            }
            try {
                $built = $kind === ArtGenerator::KIND ? app(ArtGenerator::class)->build((array) ($example['params'] ?? []), 'use', true)
                    : ($card['edit'] ?? null ? $this->edited($generator, $python, $kind, $card) : $generator->build($kind, ToolSeo::exampleParams($kind, $example), 'all', 'use', true));
            } catch (\Throwable $e) {
                $this->warn(sprintf('%s: the tool refused the parameters (%s)', $tool, mb_substr($e->getMessage(), 0, 160)));
                $failed++;

                continue;
            }
            // one-body tools take turns in the filament colours, so the catalogue is not a wall of one blue
            $turn = ['blue', 'green', 'orange', 'yellow', 'red', 'grey'][$drawn % 6];
            $colors = (array) ($card['colors'] ?? []) + (count((array) ($built['meta']['parts'] ?? [])) > 1 ? [] : ['body' => $turn]) + self::CARD_COLORS;
            $regions = (array) ($built['meta']['notes']['regions'] ?? []);
            $parts = (array) ($built['meta']['parts'] ?? []);
            // a set of separate pieces with a colour each (bins): a whole piece takes the colour of the region its middle
            // lies in, outer walls included; a plate in two colours is one piece and is split by its regions instead
            $ofRegion = function (array $p) use ($regions): ?string {
                [$cx, $cy] = [($p['bbox'][0] + $p['bbox'][3]) / 2, ($p['bbox'][1] + $p['bbox'][4]) / 2];
                foreach ($regions as $r) {
                    if ($cx >= $r['x0'] && $cx <= $r['x1'] && $cy >= $r['y0'] && $cy <= $r['y1']) {
                        return $r['color'] ?? null;
                    }
                }

                return null;
            };
            $byPiece = count($parts) > 1 && $regions && empty($regions[0]['exact']);
            $own = (array) ($built['meta']['notes']['paint'] ?? []);      // a picture in colours: the tool's own filaments
            $paint = [
                'color' => $own['body'] ?? $hex($colors['body']),
                'parts' => array_map(fn ($p) => ['tris' => $p['tris'], 'color' => $own[$p['name']] ?? $hex(($byPiece ? $ofRegion($p) : null) ?? $colors[$p['name']] ?? 'blue')], $parts),
                'regions' => $byPiece ? [] : array_map(fn ($r) => ['color' => $hex($r['color'] ?? null)] + $r, $regions),
            ];
            $paintFile = $built['path'].'.json';
            File::put($paintFile, json_encode($paint));
            $result = $python->runScript('render_tool.py', [$built['path'], $base, 'card', $paintFile], 300);
            @unlink($built['path']);
            @unlink($paintFile);
            if (empty($result['ok']) || ! is_file($base.'-800.jpg')) {
                $this->warn(sprintf('%s: not drawn (%s)', $tool, (string) ($result['error'] ?? 'no answer')));
                $failed++;

                continue;
            }
            $this->line(sprintf('%-14s card  jpg %d kB  webp %d kB / %d kB', $tool, (int) ceil(filesize($base.'-800.jpg') / 1024), (int) ceil(filesize($base.'-800.webp') / 1024), (int) ceil(filesize($base.'-480.webp') / 1024)));
            $drawn++;
        }
        $this->info("Cards drawn {$drawn}, kept {$kept}".($failed ? ", failed {$failed}" : '').'.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
