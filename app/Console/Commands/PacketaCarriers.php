<?php

namespace App\Console\Commands;

use App\Domain\Farm\CarrierBook;
use App\Domain\Farm\Shipping;
use App\Engines\Shipping\ShippingCarrier;
use App\Engines\Shipping\ShippingFailed;
use Illuminate\Console\Command;

/**
 * Downloads Packeta's list of carriers (who delivers to an address and who has pickup points, per country) into
 * storage/app/packeta_carriers.json. The farm offers delivery to a country only when a carrier for it is known,
 * from this list or from config/farm.php. The scheduler runs it weekly.
 *
 *   php artisan matplace:packeta-carriers            download and store
 *   php artisan matplace:packeta-carriers --suggest  also print the country → carrier maps to check and paste into config/farm.php
 */
class PacketaCarriers extends Command
{
    protected $signature = 'matplace:packeta-carriers {--suggest : print the maps country → carrier chosen from the list} {--dry-run : download and report, store nothing}';

    protected $description = 'Refresh the list of Packeta carriers (home delivery and partners\' pickup points)';

    public function handle(ShippingCarrier $carrier, CarrierBook $book, Shipping $shipping): int
    {
        try {
            $carriers = $carrier->carriers();
        } catch (ShippingFailed $e) {
            $this->error($e->getMessage());
            $this->line('The list in use stays as it was'.($book->updatedAt() ? ' (from '.$book->updatedAt().').' : ' (none yet: only the carriers written in config/farm.php are offered).'));

            return self::FAILURE;
        }
        // only where we send: the rest of the world would just be noise in the file
        $ours = $shipping->countries();
        $kept = array_values(array_filter($carriers, fn (array $c) => in_array($c['country'], $ours, true)));
        $this->info(count($carriers).' carriers in the feed, '.count($kept).' in the countries we send to.');

        if (! $this->option('dry-run')) {
            $book->store($kept);
            $this->line('Stored in '.CarrierBook::path().'.');
        }

        if ($this->option('suggest')) {
            if ($this->option('dry-run')) {
                $book = clone $book;
                $this->warn('--suggest reads the stored list; with --dry-run it shows the previous one.');
            }
            $maps = $book->suggest();
            foreach (['home' => 'packeta_home_carriers', 'point' => 'packeta_point_carriers'] as $type => $key) {
                $this->newLine();
                $this->line("'{$key}' => [".implode(', ', array_map(fn ($cc, $id) => "'{$cc}' => {$id}", array_keys($maps[$type]), $maps[$type])).'],');
                foreach ($maps[$type] as $cc => $id) {
                    $name = collect($book->all())->firstWhere('id', $id)['name'] ?? '?';
                    $this->line("    {$cc}  {$id}  {$name}");
                }
            }
            $missing = array_values(array_filter($ours, fn ($cc) => $book->home($cc) === null));
            if ($missing) {
                $this->newLine();
                $this->warn('No home-delivery carrier chosen for: '.implode(', ', $missing).'. Several carriers go there and none is preferred: write the id into packeta_home_carriers.');
            }
        }

        return self::SUCCESS;
    }
}
