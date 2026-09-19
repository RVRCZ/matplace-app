<?php

namespace App\Models;

use App\Engines\DTO\ModelCandidate;
use Illuminate\Database\Eloquent\Model;

class CatalogModel extends Model
{
    protected $fillable = [
        'legacy_id', 'title', 'description', 'keywords', 'source', 'external_id', 'external_url', 'preview_url', 'license',
        'author', 'category', 'est_grams', 'est_minutes', 'max_mm', 'file_available', 'model_file_id', 'active',
    ];

    protected $casts = ['file_available' => 'bool', 'active' => 'bool'];

    public function toCandidate(float $score = 0.0): ModelCandidate
    {
        return new ModelCandidate(
            source: 'local',
            externalId: (string) $this->id,
            title: $this->title,
            previewUrl: $this->preview_url,
            externalUrl: $this->external_url,
            license: $this->license,
            authorName: $this->author,
            localModelId: $this->id,
            score: $score,
            fileAvailable: $this->file_available,
        );
    }
}
