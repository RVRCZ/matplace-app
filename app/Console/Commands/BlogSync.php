<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The Czech blog articles kept in the repository (resources/blog/cs/<slug>.md) written into the posts: by slug, the
 * Czech title, excerpt and body, the format markdown, the cover and the date of the last change (dateModified,
 * the sitemap's lastmod). Never deletes; other languages stay as they are. An existing article keeps its
 * published_at (the file's date is used only for a new one, empty = a draft). An article whose other language is
 * HTML is left alone: the format is one per article, Markdown would break that translation.
 *
 * Each file starts with a header between "---" lines: title, excerpt, cover, published_at, updated_at.
 *
 *   php artisan blog:sync --dry-run
 *   php artisan blog:sync
 */
class BlogSync extends Command
{
    public const LOCALE = 'cs';

    protected $signature = 'blog:sync {--dry-run : only say what would change}';

    protected $description = 'Write the Czech blog articles from resources/blog/cs into the database (by slug, never deletes)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $changed = 0;
        foreach (self::files() as $slug => $path) {
            $article = self::parse((string) file_get_contents($path));
            $post = Post::firstOrNew(['slug' => $slug]);
            $others = array_filter(array_diff_key((array) $post->body, [self::LOCALE => 1]), fn ($b) => trim((string) $b) !== '');
            if ($post->exists && $post->format === Post::FORMAT_HTML && $others) {
                $this->warn(sprintf('%s: skipped, its %s version is HTML (Markdown would break it)', $slug, implode(', ', array_keys($others))));

                continue;
            }

            $fields = [
                'title' => [self::LOCALE => $article['title']] + (array) $post->title,
                'excerpt' => [self::LOCALE => $article['excerpt']] + (array) $post->excerpt,
                'body' => [self::LOCALE => $article['body']] + (array) $post->body,
                'format' => Post::FORMAT_MARKDOWN,
            ];
            if ($article['cover'] !== '') {
                $fields['cover_path'] = $article['cover'];
            }
            $diff = array_keys(array_filter($fields, fn ($value, $key) => $key === 'format' ? $post->format !== $value : self::differs($post->{$key}, $value), ARRAY_FILTER_USE_BOTH));
            if ($post->exists && ! $diff) {
                $this->line($slug.': unchanged');

                continue;
            }
            $changed++;
            $this->info(sprintf('%s: %s%s', $slug, $dry ? 'would ' : '', $post->exists ? 'update '.implode(', ', $diff) : 'create'.($article['published_at'] ? '' : ' (draft, no published_at)')));
            if ($dry) {
                continue;
            }
            $post->fill($fields);
            if (! $post->exists) {
                $post->fill(['published_at' => $article['published_at'], 'author_name' => 'matplace']);
            }
            // the article's own date of the last change, so a second run changes nothing
            $post->timestamps = false;
            $post->updated_at = $article['updated_at'] ?? now();
            $post->created_at ??= now();
            $post->save();
        }
        $this->info(($dry ? 'Would change ' : 'Changed ').$changed.' article(s).');

        return self::SUCCESS;
    }

    /** @return array<string, string> slug → path of every article file (the _import folder is the old export, not articles) */
    public static function files(): array
    {
        $out = [];
        foreach (glob(resource_path('blog/'.self::LOCALE.'/*.md')) ?: [] as $path) {
            $out[basename($path, '.md')] = $path;
        }
        ksort($out);

        return $out;
    }

    /**
     * The header (title, excerpt, cover, published_at, updated_at in the app's time zone, UTC; "key: value", a value
     * may be in double quotes)
     * and the Markdown body.
     *
     * @return array{title: string, excerpt: string, cover: string, published_at: ?Carbon, updated_at: ?Carbon, body: string}
     */
    public static function parse(string $text): array
    {
        $text = str_replace("\r\n", "\n", $text);
        if (! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $text, $m)) {
            throw new \InvalidArgumentException('The article has no header between --- lines.');
        }
        $head = [];
        foreach (explode("\n", $m[1]) as $line) {
            if (preg_match('/^([a-z_]+):\s*(.*)$/', $line, $kv)) {
                $value = trim($kv[2]);
                $head[$kv[1]] = preg_match('/^"(.*)"$/', $value, $q) ? stripcslashes($q[1]) : $value;
            }
        }
        $date = fn (string $key) => ($head[$key] ?? '') !== '' ? Carbon::parse($head[$key]) : null;

        return [
            'title' => (string) ($head['title'] ?? ''),
            'excerpt' => (string) ($head['excerpt'] ?? ''),
            'cover' => (string) ($head['cover'] ?? ''),
            'published_at' => $date('published_at'),
            'updated_at' => $date('updated_at'),
            'body' => trim($m[2])."\n",
        ];
    }

    private static function differs(mixed $now, mixed $new): bool
    {
        if (is_array($new)) {
            $now = (array) $now;
            ksort($now);
            ksort($new);
        }

        return $now !== $new;
    }
}
