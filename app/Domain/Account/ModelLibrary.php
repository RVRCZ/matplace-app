<?php

namespace App\Domain\Account;

use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * "My models": everything a person uploaded, generated or made with a tool, including what they made before
 * they had an account (claimed at registration). A model can be deleted unless something still needs it.
 */
final class ModelLibrary
{
    /** Filter of the list: where a model came from. */
    public const ORIGINS = ['upload', 'generated', 'tool'];

    public const LOCK_ORDER = 'order';

    public const LOCK_CARD = 'card';

    public function query(User $user, ?string $origin = null): Builder
    {
        return ModelFile::query()->where('owner_user_id', $user->id)->whereNull('deleted_at')
            ->when(in_array($origin, self::ORIGINS, true), fn (Builder $q) => $q->where('origin', $origin))
            ->latest('id');
    }

    /**
     * Why each of these models cannot be deleted (model id → reason); models that can are not in the result.
     * An order that is being made or was made needs its model; so does a card in a designer's portfolio.
     *
     * @param  Collection<int, ModelFile>|array<int, ModelFile>  $files
     * @return array<int, string>
     */
    public function locks(Collection|array $files): array
    {
        $ids = collect($files)->pluck('id')->all();
        if ($ids === []) {
            return [];
        }
        $locks = [];
        $ordered = FarmOrder::whereIn('model_file_id', $ids)->whereNotIn('status', [FarmOrder::STATUS_CANCELLED, FarmOrder::STATUS_FAILED])->distinct()->pluck('model_file_id');
        foreach (['inquiries', 'quotes'] as $table) {
            $ordered = $ordered->merge(DB::table($table)->whereIn('model_file_id', $ids)->distinct()->pluck('model_file_id'));
        }
        foreach ($ordered as $id) {
            $locks[(int) $id] = self::LOCK_ORDER;
        }
        if (Schema::hasTable('designer_models')) {
            foreach (DB::table('designer_models')->whereIn('model_file_id', $ids)->distinct()->pluck('model_file_id') as $id) {
                $locks[(int) $id] ??= self::LOCK_CARD;
            }
        }

        return $locks;
    }

    public function lock(ModelFile $file): ?string
    {
        return $this->locks([$file])[$file->id] ?? null;
    }

    /**
     * Remove the files for good. The row goes too, unless a cancelled or failed order still points to it: then it
     * stays as an empty shell (the order keeps its name), invisible in every list.
     */
    public function delete(ModelFile $file): void
    {
        Storage::disk(ModelFile::DISK)->deleteDirectory($file->dir());
        $file->calculations()->delete();
        if (FarmOrder::where('model_file_id', $file->id)->exists()) {
            $file->forceFill(['deleted_at' => now(), 'status' => ModelFile::STATUS_DELETED, 'stl_path' => null, 'preview_path' => null, 'tool_params' => null])->save();

            return;
        }
        $file->delete();
    }

    /** Account deletion: everything of this person that nothing else needs. Returns how many models went. */
    public function deleteAllOf(User $user): int
    {
        $n = 0;
        ModelFile::where('owner_user_id', $user->id)->whereNull('deleted_at')->orderBy('id')->chunkById(100, function ($files) use (&$n) {
            $locks = $this->locks($files);
            foreach ($files as $file) {
                if (! isset($locks[$file->id])) {
                    $this->delete($file);
                    $n++;
                }
            }
        });

        return $n;
    }
}
