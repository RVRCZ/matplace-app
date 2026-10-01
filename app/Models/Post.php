<?php

namespace App\Models;

use App\Support\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A blog article. It exists in the languages it has both a title and a body in; the Czech version is the one
 * every article has. The body is Markdown (written in the admin) or cleaned HTML (carried over from the old site).
 */
class Post extends Model
{
    public const FORMAT_MARKDOWN = 'markdown';

    public const FORMAT_HTML = 'html';

    protected $fillable = ['legacy_id', 'slug', 'title', 'excerpt', 'body', 'format', 'cover_path', 'author_id', 'author_name', 'published_at'];

    protected $casts = ['title' => 'array', 'excerpt' => 'array', 'body' => 'array', 'published_at' => 'datetime'];

    protected static function booted(): void
    {
        // which languages have a blog at all is remembered (BlogController::languages)
        static::saved(fn () => Cache::forget('blog.languages'));
        static::deleted(fn () => Cache::forget('blog.languages'));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    /** @return list<string> the languages the article can be read in */
    public function locales(): array
    {
        return array_values(array_filter(Locales::SUPPORTED, fn (string $l) => trim((string) ($this->title[$l] ?? '')) !== '' && trim((string) ($this->body[$l] ?? '')) !== ''));
    }

    public function titleIn(?string $locale = null): string
    {
        return (string) ($this->title[$locale ?? app()->getLocale()] ?? $this->title[Locales::DEFAULT] ?? '');
    }

    public function excerptIn(?string $locale = null): string
    {
        $text = trim((string) ($this->excerpt[$locale ?? app()->getLocale()] ?? ''));

        return $text !== '' ? $text : Str::limit(trim((string) preg_replace('/\s+/u', ' ', strip_tags($this->html($locale)))), 200);
    }

    /** The body as HTML that is safe to print: Markdown rendered without raw HTML, carried-over HTML as it was cleaned on import. */
    public function html(?string $locale = null): string
    {
        $body = (string) ($this->body[$locale ?? app()->getLocale()] ?? '');

        return $this->format === self::FORMAT_HTML ? $body : Str::markdown($body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    public function coverUrl(): ?string
    {
        if (! $this->cover_path) {
            return null;
        }

        return preg_match('#^https?://#', $this->cover_path) ? $this->cover_path : Storage::disk('public')->url($this->cover_path);
    }

    /** The picture as a file on this machine (for link previews); null when it lives elsewhere. */
    public function coverFile(): ?string
    {
        if (! $this->cover_path || preg_match('#^https?://#', $this->cover_path)) {
            return null;
        }
        $path = Storage::disk('public')->path($this->cover_path);

        return is_file($path) ? $path : null;
    }

    public function publicUrl(?string $locale = null): string
    {
        return localized_route('blog.show', ['post' => $this->slug], $locale);
    }

    /** Minutes to read, at 200 words a minute. */
    public function readingMinutes(?string $locale = null): int
    {
        return max(1, (int) round(str_word_count(strip_tags($this->html($locale))) / 200));
    }
}
