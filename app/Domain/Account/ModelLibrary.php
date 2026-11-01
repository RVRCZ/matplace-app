<?php

namespace App\Domain\Account;

use App\Models\FarmOrder;
use App\Models\ModelFile;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

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
     * Files nothing holds any more, older than uploads.unordered_retention_days (matplace:prune, docs/AL.md §16.5).
     * Held by an inquiry, a quote (or their calculation), the catalogue, a designer's card, an article that shows the
     * calculation, or a paid order. A file with an account also by any order and by the generator's request it came
     * from; a file without one goes with its orders nobody paid and that were cancelled, and with its request.
     *
     * @return LazyCollection<int, ModelFile>
     */
    public function unordered(): LazyCollection
    {
        $linked = fn (string $table, string $column = 'model_file_id') => DB::table($table)->whereNotNull($column)->select($column);
        $byCalculation = fn (string $table) => DB::table('calculations')->whereIn('id', $linked($table, 'calculation_id'))->select('model_file_id');
        $tokens = Post::query()->pluck('body')->flatMap(function ($body) {
            preg_match_all('~(?:\[calc:\s*|/c/)([A-Za-z0-9_-]+)~', implode("\n", array_filter((array) $body, 'is_string')), $m);

            return $m[1];
        })->unique()->values()->all();

        return ModelFile::where('created_at', '<', now()->subDays(max(1, (int) config('uploads.unordered_retention_days', 30))))
            ->whereNotIn('id', $linked('inquiries'))
            ->whereNotIn('id', $linked('quotes'))
            ->whereNotIn('id', $byCalculation('inquiries'))
            ->whereNotIn('id', $byCalculation('quotes'))
            ->whereNotIn('id', $linked('catalog_models'))
            ->whereNotIn('id', $linked('designer_models'))
            ->whereNotIn('id', DB::table('calculations')->whereIn('token', $tokens)->select('model_file_id'))
            ->where(fn ($q) => $q
                ->where(fn ($own) => $own->whereNotNull('owner_user_id')
                    ->whereNotIn('id', $linked('farm_orders'))
                    ->whereNotIn('id', $linked('generation_requests', 'result_model_file_id')))
                ->orWhere(fn ($anon) => $anon->whereNull('owner_user_id')
                    ->whereNotIn('id', DB::table('farm_orders')->where(fn ($o) => $o->whereNotNull('paid_at')->orWhere('status', '!=', FarmOrder::STATUS_CANCELLED))->select('model_file_id'))))
            ->lazyById(200);
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
