<?php

namespace App\Domain\Designer;

use App\Jobs\PrepareDesignerFile;
use App\Jobs\ProcessModelFile;
use App\Models\DesignerModel;
use App\Models\ModelFile;
use App\Models\User;
use App\Support\Track;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The file behind a card: what the farm prints. Accepted: STL, 3MF, or a zip (its first model file is taken;
 * joining several parts into one model is not something the pipeline can do yet, the other names are reported back).
 * The upload carries the author's word ("I am the author and let matplace print this for customers"); a remix
 * also needs the word about the original's licence first. The file then goes through the same check as the
 * "check my model" tool and gets its size, weight and time from the slicer (PrepareDesignerFile).
 */
final class CardFiles
{
    public const MODEL_EXTENSIONS = ['stl', '3mf'];

    public const MAX_FILE_MB = 100;

    public const MAX_ZIP_MB = 500;

    /**
     * Attach a file that already lies on this machine (an upload's temporary file, or one taken out of a zip).
     *
     * @return array{ok: bool, error?: string, others?: list<string>} error = code for designer.file.error.<code>
     */
    public function attach(DesignerModel $card, string $path, string $name, User $user): array
    {
        if ($card->needsRemixConfirmation()) {
            return ['ok' => false, 'error' => 'remix'];
        }
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $others = [];
        $cleanup = null;
        if ($ext === 'zip') {
            $entries = $this->entries($path);
            if (! $entries) {
                return ['ok' => false, 'error' => 'zip_empty'];
            }
            $name = $entries[0];
            $others = array_slice($entries, 1);
            $path = $cleanup = $this->extract($path, $name) ?? '';
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($path === '') {
                return ['ok' => false, 'error' => 'zip_empty'];
            }
        }
        if (! in_array($ext, self::MODEL_EXTENSIONS, true)) {
            return ['ok' => false, 'error' => 'format'];
        }
        if (filesize($path) > self::MAX_FILE_MB * 1024 * 1024) {
            $cleanup && @unlink($cleanup);

            return ['ok' => false, 'error' => 'too_big'];
        }

        $uuid = (string) Str::uuid();
        $stored = 'files/'.$uuid.'/original.'.$ext;
        $target = Storage::disk(ModelFile::DISK)->path($stored);
        File::ensureDirectoryExists(dirname($target));
        File::copy($path, $target);
        $cleanup && @unlink($cleanup);

        $file = ModelFile::create([
            'uuid' => $uuid, 'owner_user_id' => $user->id, 'original_name' => mb_substr(basename(str_replace('\\', '/', $name)), 0, 255), 'ext' => $ext,
            'size_bytes' => filesize($target), 'sha256' => hash_file('sha256', $target), 'storage_path' => $stored,
            'origin' => 'portfolio', 'origin_ref' => (string) $card->id, 'status' => ModelFile::STATUS_UPLOADED,
        ]);
        // the card shows "being checked" until the file has passed; the old file (if any) keeps serving meanwhile
        $card->forceFill(['file_status' => DesignerModel::FILE_CHECKING, 'file_check' => null, 'author_confirmed_at' => now()])->save();
        Track::event('designer_file_uploaded', $card);
        // a designer's files come by the dozen (a ZIP of the portfolio): not in front of a customer's upload
        ProcessModelFile::dispatch($file->id)->onQueue('default');
        PrepareDesignerFile::dispatch($card->id, $file->id);

        return ['ok' => true, 'others' => array_map(fn (string $e) => basename($e), $others)];
    }

    /** Take the file away from a card: the card stays in the portfolio, the catalogue no longer shows it. */
    public function detach(DesignerModel $card): void
    {
        $card->forceFill(['model_file_id' => null, 'file_status' => DesignerModel::FILE_NONE, 'file_check' => null, 'slice_summary' => null, 'author_confirmed_at' => null])->save();
    }

    /**
     * Names of the model files inside a zip, in the order they are stored. Hidden files and the __MACOSX folder are not models.
     *
     * @return list<string>
     */
    public function entries(string $zipPath): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return [];
        }
        $out = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            $base = basename($name);
            if (str_ends_with($name, '/') || str_starts_with($base, '.') || str_contains($name, '__MACOSX/')) {
                continue;
            }
            if (in_array(strtolower(pathinfo($base, PATHINFO_EXTENSION)), self::MODEL_EXTENSIONS, true)) {
                $out[] = $name;
            }
        }
        $zip->close();

        return $out;
    }

    /** One entry of a zip as a temporary file; null when it cannot be read or is larger than a model may be. */
    public function extract(string $zipPath, string $entry): ?string
    {
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return null;
        }
        $stat = $zip->statName($entry);
        $in = $stat && $stat['size'] <= self::MAX_FILE_MB * 1024 * 1024 ? $zip->getStream($entry) : false;
        if (! $in) {
            $zip->close();

            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'mpcard');
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        $zip->close();

        return $tmp;
    }
}
