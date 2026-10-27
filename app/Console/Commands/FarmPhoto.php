<?php

namespace App\Console\Commands;

use App\Domain\Farm\PrintPhotos;
use App\Domain\Farm\TestPhotos;
use App\Engines\Exceptions\EngineException;
use App\Jobs\AddFinishPhotoToVideo;
use App\Models\FarmOrder;
use App\Models\FarmVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Photos of finished prints from the photo box, made presentable (PrintPhotos, docs/AL.md §13).
 *
 *   php artisan farm:photo --probe                                        does the background remover work here?
 *   php artisan farm:photo photo.jpg --order=F26-000123 [--bg=wood]      the order's photo: cut, backdrops, videos rebuilt
 *   php artisan farm:photo "C:\Users\roman\Pictures\Camera Roll" --match=20261010 [--out=folder] [--bg=studio]
 *                                                                          a batch into a folder (Roman's PC), to upload later
 *   php artisan farm:photo --redo-videos [--apply] [--all] [--ids=F26-000097]
 *                                                                          customer prints photographed in the photo box composed
 *                                                                          again (missing, fitted or empty; a plan without --apply)
 */
class FarmPhoto extends Command
{
    protected $signature = 'farm:photo {path? : a photo, or a folder of photos (the batch)}
        {--order= : the number (F26-000123) or token of the order the photo belongs to}
        {--bg= : studio | wood | white | gradient | paper | marble | concrete | linen (studio when left out)}
        {--match= : in a folder only the files whose name holds this (a date like 20261010)}
        {--out= : where the batch goes (storage/app/photos-out/<today> when left out)}
        {--probe : say whether the background remover works here and stop}
        {--redo-videos : customer prints photographed in the photo box whose composed photo is missing or keeps its background, a plan}
        {--apply : with --redo-videos, compose them and have their videos built again}
        {--all : with --redo-videos, the prints with a cut photo too (another backdrop, a newer rembg)}
        {--ids= : with --redo-videos, only these orders (numbers or tokens, comma-separated), whatever their state}';

    protected $description = 'Take the background off a photo of a finished print and put the piece on a backdrop, for one order or a whole folder';

    public function handle(PrintPhotos $photos, TestPhotos $testPhotos): int
    {
        if ($this->option('probe')) {
            // the truth in two halves: rembg installed, the model in its place (without the model nothing is cut)
            $probe = $photos->probe();
            $this->line('engine: '.config('engines.photo').' · python: '.config('engines.python.bin').' · U2NET_HOME: '.config('engines.photo_home').' · NUMBA_CACHE_DIR: '.config('engines.photo_numba_cache'));
            $this->line('rembg: '.(! empty($probe['ok']) ? '<info>yes</info>'.(isset($probe['rembg']) ? ' ('.$probe['rembg'].')' : '') : '<comment>no</comment>'.(isset($probe['error']) ? ' – '.$probe['error'] : '')));
            $this->line('model u2net.onnx: '.(! empty($probe['model_present']) ? '<info>yes</info>' : '<comment>no</comment>'.(isset($probe['home']) ? ' (looked in '.$probe['home'].')' : '')));
            $this->line('background remover: '.($photos->available() ? '<info>available</info>' : '<comment>not available – photos are fitted as they are</comment>'));

            return $photos->available() ? self::SUCCESS : self::FAILURE;
        }
        $bg = $this->option('bg') ?: null;
        if ($bg !== null && ! in_array($bg, PrintPhotos::BACKGROUNDS, true)) {
            $this->error('Unknown backdrop; one of '.implode(', ', PrintPhotos::BACKGROUNDS).'.');

            return self::FAILURE;
        }
        if ($this->option('redo-videos')) {
            $ids = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('ids')))));

            return $this->redo($photos, $testPhotos, $bg, (bool) $this->option('apply'), (bool) $this->option('all'), $ids);
        }
        $path = (string) $this->argument('path');
        if ($path === '' || ! file_exists($path)) {
            $this->error('Give a photo or a folder of photos.');

            return self::FAILURE;
        }
        if (! $photos->available()) {
            $this->warn('The background remover is not available here (rembg): the photos are fitted as they are.');
        }
        $files = $this->files($path, (string) $this->option('match'));
        if (! $files) {
            $this->error('No photo found.');

            return self::FAILURE;
        }

        if ($number = (string) $this->option('order')) {
            $order = FarmOrder::where('number', $number)->orWhere('token', $number)->first();
            if (! $order) {
                $this->error("No order {$number}.");

                return self::FAILURE;
            }
            // a folder given with an order: its newest photo is the one
            $source = end($files);
            try {
                $meta = $photos->attach($order, $source, $bg);
            } catch (EngineException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }
            $this->info(($order->number ?: $order->token).': '.basename($source).' → '.$meta['files']['web'].($meta['cut'] ? ' (cut, '.round(100 * (float) $meta['coverage']).' % piece)' : ' (not cut)'));
            if ($order->timelapse_path) {
                AddFinishPhotoToVideo::dispatch($order->id);
                $this->line('the videos are being built again with the photo');
            }

            return self::SUCCESS;
        }

        $out = (string) ($this->option('out') ?: storage_path('app/photos-out/'.now()->format('Y-m-d')));
        $rows = [];
        foreach ($files as $file) {
            $stem = pathinfo($file, PATHINFO_FILENAME);
            try {
                $r = $photos->render($file, $out, $stem, $bg);
                $rows[] = [basename($file), $r['cut'] ? 'yes ('.round(100 * (float) $r['coverage']).' %)' : 'no', basename($r['files']['web'])];
            } catch (EngineException $e) {
                $rows[] = [basename($file), 'error', $e->getMessage()];
            }
        }
        $this->table(['photo', 'cut', 'web'], $rows);
        $this->info(count($rows).' photos → '.$out);

        return self::SUCCESS;
    }

    /**
     * The customers' prints photographed in the photo box before the composed photo existed, while rembg had no
     * model (the photo fitted with its background on) or before the empty-cut threshold (PrintPhotos::cutState none,
     * fitted, empty): composed now from the photo they were made from (else the one the videos end with today), their
     * videos built again. A plan unless --apply; --all takes the cut and the uncut ones too; --ids names the orders
     * and takes them whatever their state.
     *
     * @param  list<string>  $ids
     */
    private function redo(PrintPhotos $photos, TestPhotos $testPhotos, ?string $bg, bool $apply, bool $all, array $ids = []): int
    {
        if ($apply && ! $photos->available()) {
            $this->error('The background remover is not available here (farm:photo --probe): a redo would only fit the photos again.');

            return self::FAILURE;
        }
        $disk = Storage::disk(config('farm.disk'));
        $rows = [];
        $todo = 0;
        $orders = FarmOrder::whereNotNull('test_params')->orderBy('id');
        if ($ids) {
            $orders->where(fn ($q) => $q->whereIn('number', $ids)->orWhereIn('token', $ids));
        }
        $orders = $orders->get();
        foreach (array_diff($ids, $orders->pluck('number')->all(), $orders->pluck('token')->all()) as $missing) {
            $this->warn("No order {$missing}.");
        }
        foreach ($orders as $order) {
            $finish = $order->isTest() ? null : $testPhotos->finishFile($order);
            if ($finish === null) {
                if ($ids) {
                    $this->warn(($order->number ?: $order->token).': no photo from the photo box.');
                }

                continue;
            }
            $from = (string) ($order->photo_meta['source'] ?? '');
            $source = $from !== '' && $disk->exists($from) ? $from : $finish;
            $state = ! $order->hasPhoto() ? 'none' : PrintPhotos::cutState((array) $order->photo_meta);
            $videos = $order->videos()->whereIn('status', [FarmVideo::STATUS_UPLOADED, FarmVideo::STATUS_SCHEDULED])->count();
            $number = $order->number ?: $order->token;
            if (in_array($state, ['cut', 'uncut'], true) && ! $all && ! $ids) {
                $rows[] = [$number, basename($source), $state, $videos, 'kept'];

                continue;
            }
            $todo++;
            if (! $apply) {
                $rows[] = [$number, basename($source), $state, $videos, 'to compose'.($order->timelapse_path ? ' + videos' : '')];

                continue;
            }
            try {
                $meta = $photos->attach($order, $disk->path($source), $bg, $source);
                $rows[] = [$number, basename($source), $state, $videos, $meta['cut'] ? 'cut ('.round(100 * (float) $meta['coverage']).' % piece)' : 'fitted'.($meta['cut_error'] ? ' – '.$meta['cut_error'] : '')];
                if ($order->timelapse_path) {
                    AddFinishPhotoToVideo::dispatch($order->id);
                }
            } catch (EngineException $e) {
                $rows[] = [$number, basename($source), $state, $videos, 'error: '.$e->getMessage()];
            }
        }
        $this->table(['order', 'photo', 'today', 'videos to replace', $apply ? 'result' : 'plan'], $rows);
        if (! $apply) {
            $this->info($todo.' to compose'.($todo ? '; run again with --apply to do it (the videos of each are built again, uploaded copies not yet public are replaced; published ones stay, /admin/youtube replaces them)' : ''));
        } else {
            $this->info($todo.' composed; the videos are being built again by the queue');
        }

        return self::SUCCESS;
    }

    /** @return list<string> the photos, oldest first (a folder), or the one file */
    private function files(string $path, string $match): array
    {
        if (is_file($path)) {
            return [$path];
        }
        $files = array_filter(glob(rtrim($path, '\\/').'/*.{jpg,jpeg,png,JPG,JPEG,PNG,heic,HEIC}', GLOB_BRACE) ?: [], fn ($f) => $match === '' || str_contains(basename($f), $match));
        sort($files);

        return array_values($files);
    }
}
