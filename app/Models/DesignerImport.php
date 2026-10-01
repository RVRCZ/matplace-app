<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One run of "bring my cards over from Printables / MakerWorld". Every item is imported by its own queue job;
 * the page of the import polls `progress()` every few seconds.
 */
class DesignerImport extends Model
{
    public const MAX_ITEMS = 100;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const ITEM_WAITING = 'waiting';

    public const ITEM_IMPORTED = 'imported';

    public const ITEM_SKIPPED = 'skipped';   // already in the portfolio

    public const ITEM_FAILED = 'failed';

    protected $fillable = ['designer_profile_id', 'source', 'status', 'total', 'done', 'failed', 'items', 'log'];

    protected $casts = ['items' => 'array', 'log' => 'array', 'total' => 'int', 'done' => 'int', 'failed' => 'int'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DesignerProfile::class, 'designer_profile_id');
    }

    /** Write the outcome of one item and move the counters; the last item closes the run. */
    public function finishItem(int $index, string $state, ?string $note = null, ?int $modelId = null): void
    {
        $items = (array) $this->items;
        if (! isset($items[$index]) || ($items[$index]['state'] ?? self::ITEM_WAITING) !== self::ITEM_WAITING) {
            return;
        }
        $items[$index] = ['state' => $state, 'note' => $note, 'model_id' => $modelId] + $items[$index];
        $this->items = $items;
        $this->done = count(array_filter($items, fn ($i) => in_array($i['state'], [self::ITEM_IMPORTED, self::ITEM_SKIPPED], true)));
        $this->failed = count(array_filter($items, fn ($i) => $i['state'] === self::ITEM_FAILED));
        $this->status = $this->done + $this->failed >= $this->total ? self::STATUS_DONE : self::STATUS_RUNNING;
        $this->save();
    }

    /** @return array<string, mixed> what the progress page shows */
    public function progress(): array
    {
        return [
            'status' => $this->status, 'total' => $this->total, 'done' => $this->done, 'failed' => $this->failed,
            'items' => array_map(fn ($i) => array_intersect_key($i, array_flip(['title', 'url', 'state', 'note'])), (array) $this->items),
        ];
    }
}
