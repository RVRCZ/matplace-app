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

    /** A web address a visitor can open; the index also holds non-web references (drive folders). */
    public function hasWebLink(): bool
    {
        return (bool) preg_match('~^https?://~i', (string) $this->external_url);
    }

    /** Named after the site the link opens, never after us: "Open on matplace" must not lead to Printables. */
    public function linkOrigin(): ?string
    {
        if (! $this->hasWebLink()) {
            return null;
        }
        $host = strtolower((string) parse_url((string) $this->external_url, PHP_URL_HOST));
        foreach (['printables' => 'printables', 'makerworld' => 'makerworld', 'makeronline' => 'makeronline', 'cults3d' => 'cults3d', 'thingiverse' => 'thingiverse', 'thangs' => 'thangs'] as $needle => $origin) {
            if (str_contains($host, $needle)) {
                return $origin;
            }
        }

        return preg_replace('/^www\./', '', $host) ?: null;
    }

    public function toCandidate(float $score = 0.0): ModelCandidate
    {
        return new ModelCandidate(
            source: 'local',
            externalId: (string) $this->id,
            title: $this->title,
            previewUrl: $this->preview_url,
            externalUrl: $this->hasWebLink() ? $this->external_url : null,
            license: $this->license,
            authorName: $this->author,
            localModelId: $this->id,
            score: $score,
            fileAvailable: $this->file_available,
            origin: $this->linkOrigin(),
        );
    }
}
