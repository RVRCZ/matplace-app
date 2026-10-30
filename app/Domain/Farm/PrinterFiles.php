<?php

namespace App\Domain\Farm;

use App\Models\FarmCommand;
use App\Models\FarmPrinter;
use App\Models\FarmPrintJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The files on a printer's disk as the admin sees them (docs/AM.md §10): the list the agent sent last (names, sizes,
 * dates, free space, the last cleanup's summary, kept as one JSON on the printer), the copies the agent brought up to
 * the server (storage/app/farm/printers/{id}/files/), and the rules: a file of a job that runs or waits is never
 * deleted, a cleanup takes only the farm's own files (mp<job>-matplace-*.gcode), one file at a time comes up, 300 MB
 * at most.
 */
final class PrinterFiles
{
    /** The most the server takes from the agent for one file (bytes); the agent refuses bigger ones before the download. */
    public const MAX_BYTES = 300 * 1024 * 1024;

    /** How the agent names every print it uploads: mp<job id>-<the server's remote_filename>. */
    public const OURS = '/^mp\d+-matplace-.*\.gcode(\.3mf)?$/';

    /** A file name as the agent and the admin may use it: a plain name, no folder, no way up, nothing unprintable. */
    public static function plain(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '' || $name === '.' || $name === '..' || mb_strlen($name) > 200 || basename($name) !== $name
            || str_contains($name, '/') || str_contains($name, '\\') || preg_match('/[\x00-\x1f\x7f]/', $name)) {
            return null;
        }

        return $name;
    }

    public static function isOurs(string $name): bool
    {
        return (bool) preg_match(self::OURS, $name);
    }

    /** The agent's names of the files of the jobs that run or wait on this printer: never deleted, kept by a cleanup. */
    public function keep(FarmPrinter $printer): array
    {
        return $printer->printJobs()->whereIn('status', FarmPrintJob::ACTIVE)->get()
            ->map(fn (FarmPrintJob $j) => 'mp'.$j->id.'-'.($j->remote_filename ?: 'print.gcode'))->values()->all();
    }

    /** @return array{files: list<array{name: string, size: int, modified: int}>, free: ?int, total: ?int, deleted: list<array{name: string, size: int}>, freed: int, at: ?string, cleaned_at: ?string} */
    public function listing(FarmPrinter $printer): array
    {
        $f = (array) ($printer->files ?? []);

        return [
            'files' => array_values((array) ($f['files'] ?? [])), 'free' => $f['free'] ?? null, 'total' => $f['total'] ?? null,
            'deleted' => array_values((array) ($f['deleted'] ?? [])), 'freed' => (int) ($f['freed'] ?? 0), 'at' => $f['at'] ?? null, 'cleaned_at' => $f['cleaned_at'] ?? null,
        ];
    }

    /** What the agent sent, trimmed to what the admin page shows, stored whole on the printer. */
    public function store(FarmPrinter $printer, array $data): void
    {
        $files = [];
        foreach (array_slice((array) ($data['files'] ?? []), 0, 3000) as $f) {
            $name = is_array($f) ? self::plain($f['name'] ?? null) : null;
            if ($name !== null) {
                $files[] = ['name' => $name, 'size' => max(0, (int) ($f['size'] ?? 0)), 'modified' => max(0, (int) ($f['modified'] ?? 0))];
            }
        }
        $deleted = [];
        foreach (array_slice((array) ($data['deleted'] ?? []), 0, 500) as $d) {
            $name = is_array($d) ? self::plain($d['name'] ?? null) : null;
            if ($name !== null) {
                $deleted[] = ['name' => $name, 'size' => max(0, (int) ($d['size'] ?? 0))];
            }
        }
        $listing = ['files' => $files, 'free' => isset($data['free']) && is_numeric($data['free']) ? (int) $data['free'] : null,
            'total' => isset($data['total']) && is_numeric($data['total']) ? (int) $data['total'] : null, 'at' => now()->toIso8601String()];
        if (array_key_exists('deleted', $data)) {
            $listing += ['deleted' => $deleted, 'freed' => max(0, (int) ($data['freed'] ?? 0)), 'cleaned_at' => now()->toIso8601String()];
        } else {
            // a plain list keeps the last cleanup's summary, so the admin still sees what went
            $old = (array) ($printer->files ?? []);
            $listing += array_intersect_key($old, array_flip(['deleted', 'freed', 'cleaned_at']));
        }
        $printer->update(['files' => $listing, 'files_at' => now()]);
    }

    /** Where a printer's fetched files live on the farm disk. */
    public static function dir(FarmPrinter $printer): string
    {
        return 'printers/'.$printer->id.'/files';
    }

    /** One file the agent fetched from the printer, into the printer's folder; returns its size. */
    public function save(FarmPrinter $printer, UploadedFile $file, string $name): int
    {
        $size = (int) $file->getSize();
        Storage::disk(config('farm.disk'))->putFileAs(self::dir($printer), $file, $name);

        return $size ?: (int) Storage::disk(config('farm.disk'))->size(self::dir($printer).'/'.$name);
    }

    /** @return list<array{name: string, size: int, modified: int}> the copies on the server, newest first */
    public function fetched(FarmPrinter $printer): array
    {
        $disk = Storage::disk(config('farm.disk'));
        $out = [];
        foreach ($disk->files(self::dir($printer)) as $path) {
            $out[] = ['name' => basename($path), 'size' => (int) $disk->size($path), 'modified' => (int) $disk->lastModified($path)];
        }
        usort($out, fn ($a, $b) => $b['modified'] <=> $a['modified']);

        return $out;
    }

    public function fetchedPath(FarmPrinter $printer, string $name): ?string
    {
        $rel = self::dir($printer).'/'.$name;

        return self::plain($name) !== null && Storage::disk(config('farm.disk'))->exists($rel) ? Storage::disk(config('farm.disk'))->path($rel) : null;
    }

    public function forget(FarmPrinter $printer, string $name): bool
    {
        return self::plain($name) !== null && Storage::disk(config('farm.disk'))->delete(self::dir($printer).'/'.$name);
    }

    /** The file commands of the last day, newest first: what waits for the agent, what it did and what it said. */
    public function commands(FarmPrinter $printer)
    {
        return FarmCommand::where('farm_printer_id', $printer->id)->whereIn('type', FarmCommand::FILE_TYPES)
            ->where('created_at', '>', now()->subDay())->latest('id')->limit(12)->get();
    }

    /** Human size: 1.2 GB, 345 MB, 12 kB. */
    public static function size(?int $bytes): string
    {
        $bytes = (int) $bytes;
        if ($bytes >= 1 << 30) {
            return number_format($bytes / (1 << 30), 2, ',', ' ').' GB';
        }
        if ($bytes >= 1 << 20) {
            return number_format($bytes / (1 << 20), 1, ',', ' ').' MB';
        }

        return number_format($bytes / 1024, 0, ',', ' ').' kB';
    }
}
