<?php

namespace App\Console\Commands;

use App\Domain\Tools\ParametricGenerator;
use App\Engines\Repair\PythonTool;
use App\Support\ToolSeo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Draws the examples shown under a tool's page: for every example in config/tools.php (`seo.examples`) the tool
 * itself builds the model from those parameters and engines/python/render_tool.py draws it. The pictures are kept in
 * the repository (public/img/tool-examples/<tool>-<n>.png); run this again after a tool's geometry or an example changes.
 *
 *   php artisan matplace:tool-examples            every tool, only pictures that are missing
 *   php artisan matplace:tool-examples vase box   just these tools
 *   php artisan matplace:tool-examples --force    draw again what already exists
 */
class ToolExamples extends Command
{
    protected $signature = 'matplace:tool-examples {tools?* : tool keys (default: all)} {--force : draw again what already exists}';

    protected $description = 'Draw the example pictures of the tools (public/img/tool-examples)';

    public function handle(ParametricGenerator $generator, PythonTool $python): int
    {
        if (! $generator->available()) {
            $this->error('The model generator needs Python with trimesh and manifold3d (engines/python).');

            return self::FAILURE;
        }
        $only = (array) $this->argument('tools');
        $drawn = $kept = $failed = 0;
        foreach ((array) config('tools') as $tool => $definition) {
            $examples = (array) ($definition['seo']['examples'] ?? []);
            if (! $examples || ($only && ! in_array($tool, $only, true)) || ! isset(ParametricGenerator::FIELDS[$tool])) {
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
                    $built = $generator->build($tool, ToolSeo::exampleParams($tool, $example), 'all', 'use');
                } catch (\Throwable $e) {
                    $this->warn(sprintf('%s #%d: the tool refused the parameters (%s)', $tool, $i + 1, mb_substr($e->getMessage(), 0, 160)));
                    $failed++;

                    continue;
                }
                File::ensureDirectoryExists(dirname($target));
                $result = $python->runScript('render_tool.py', [$built['path'], $target, '800', '600'], 180);
                @unlink($built['path']);
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
}
