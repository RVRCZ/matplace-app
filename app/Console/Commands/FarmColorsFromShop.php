<?php

namespace App\Console\Commands;

use App\Domain\Farm\ColorCatalog;
use App\Domain\Farm\FilamentFromShop;
use App\Models\FarmColor;
use Illuminate\Console\Command;

/**
 * Colours with a shop link read again from their pages, in one go (TODO #71 E, docs/AL.md §21.8): the photo when a colour
 * has none of its own, the hex when it is unset, the English name when empty – the same as the admin's "Načíst znovu",
 * never the settings. Every colour is one page and one model call (crowns each); requests to the same shop are 2 s apart.
 *
 *   php artisan farm:colors-from-shop --missing     only colours that lack a photo of their own, a hex or an English name
 *   php artisan farm:colors-from-shop               every colour with a link (the link's record is refreshed too)
 *   php artisan farm:colors-from-shop --dry-run     read and ask, write nothing
 */
class FarmColorsFromShop extends Command
{
    protected $signature = 'farm:colors-from-shop {--missing : only colours without a photo of their own, without a hex or without an English name} {--dry-run : read the pages, write nothing} {--limit=0 : at most this many colours}';

    protected $description = 'Fill what colours with a shop link lack (photo, hex, English name) from their product pages';

    /** Between two requests to the same host. */
    public const GAP_MS = 2000;

    /** @var (\Closure(int): void)|null tests: what to do instead of sleeping for the given milliseconds */
    public static ?\Closure $sleeper = null;

    public function handle(FilamentFromShop $agent): int
    {
        $dry = (bool) $this->option('dry-run');
        $colors = FarmColor::with('material')->whereNotNull('source_url')->orderBy('id')->get();
        if ($this->option('missing')) {
            // a shop photo counts as a photo here, so repeated runs converge (the admin's "Načíst znovu" may still replace it)
            $colors = $colors->filter(fn (FarmColor $c) => ! $c->photo_path || ColorCatalog::hexMissing($c) || trim((string) $c->name_en) === '');
        }
        if ((int) $this->option('limit') > 0) {
            $colors = $colors->take((int) $this->option('limit'));
        }
        $filled = ['photo' => 0, 'hex' => 0, 'name_en' => 0];
        $done = $skipped = 0;
        $last = [];
        foreach ($colors as $color) {
            $host = strtolower((string) parse_url((string) $color->source_url, PHP_URL_HOST));
            if (isset($last[$host])) {
                $wait = self::GAP_MS - (int) round((microtime(true) - $last[$host]) * 1000);
                if ($wait > 0) {
                    self::pause($wait);
                }
            }
            $last[$host] = microtime(true);
            $r = $agent->refreshNow($color, $dry);
            $label = (string) ($color->code ?: '#'.$color->id.' '.$color->name);
            if ($r['status'] === 'done' || $r['status'] === 'dry') {
                if ($r['filled'] === []) {
                    $skipped++;
                    $this->line(sprintf('<comment>skipped</comment>  %-44s nothing to fill%s', $label, $r['warnings'] ? ' ('.implode(', ', $r['warnings']).')' : ''));

                    continue;
                }
                $done++;
                foreach ($r['filled'] as $what) {
                    $filled[$what] = ($filled[$what] ?? 0) + 1;
                }
                $fresh = $color->fresh();
                $this->line(sprintf('filled   %-44s %s', $label, implode(', ', array_map(fn ($w) => $w === 'hex' ? 'hex '.($dry ? '' : $fresh->hex) : $w, $r['filled']))));

                continue;
            }
            $skipped++;
            $this->line(sprintf('<comment>skipped</comment>  %-44s %s', $label, $r['message'] ?: implode(', ', $r['warnings']) ?: $r['status']));
        }
        $this->info(($dry ? '[dry-run] ' : '').sprintf('filled %d (photo %d, hex %d, name_en %d), skipped %d', $done, $filled['photo'], $filled['hex'], $filled['name_en'], $skipped));

        return self::SUCCESS;
    }

    private static function pause(int $ms): void
    {
        if (self::$sleeper) {
            (self::$sleeper)($ms);

            return;
        }
        usleep($ms * 1000);
    }
}
