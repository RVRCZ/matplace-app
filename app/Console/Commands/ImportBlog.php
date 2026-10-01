<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Support\HtmlCleaner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Brings the old site's blog over: published articles with the SAME slugs (so /blog/{slug} keeps its address),
 * Czech only. The text is cleaned of the old site's styles; pictures are copied from the old site's folder into our
 * storage (blog/…). Reads the old database only (connection "legacy"), never writes there; can be run again.
 *
 *   php artisan matplace:import-blog --dry-run
 *   php artisan matplace:import-blog [--images=/var/www/matplace/storage/blog-images]
 */
class ImportBlog extends Command
{
    protected $signature = 'matplace:import-blog {--connection=legacy} {--dry-run} {--images= : folder with the old site\'s blog pictures} {--assets= : address the pictures are shown from when they cannot be copied}';

    protected $description = 'Import the old site\'s blog articles (same addresses, Czech only)';

    public function handle(): int
    {
        $legacy = DB::connection($this->option('connection'));
        $dry = (bool) $this->option('dry-run');
        $images = rtrim((string) ($this->option('images') ?: config('catalog.legacy_blog_images')), '/\\');
        $assets = rtrim((string) ($this->option('assets') ?: config('catalog.legacy_assets_url')), '/');
        $copied = 0;

        // /blog-img/<file> → our storage when the file can be copied, else the old site's address
        $picture = function (string $src) use ($images, $assets, $dry, &$copied): ?string {
            if (! preg_match('#^(?:https?://[^/]+)?/blog-img/([A-Za-z0-9._-]+)$#', $src, $m)) {
                return preg_match('#^https?://#', $src) ? $src : null;
            }
            $file = $images.'/'.$m[1];
            if ($images !== '' && is_file($file)) {
                if (! $dry && ! Storage::disk('public')->exists('blog/'.$m[1])) {
                    Storage::disk('public')->put('blog/'.$m[1], (string) file_get_contents($file));
                }
                $copied++;

                return Storage::disk('public')->url('blog/'.$m[1]);
            }

            return $assets.'/blog-img/'.$m[1];
        };

        $rows = $legacy->table('blog_posts')->where('published', 1)->orderBy('id')->get();
        foreach ($rows as $r) {
            $body = HtmlCleaner::clean((string) $r->content, $picture);
            $cover = null;
            if (preg_match('#/blog-img/([A-Za-z0-9._-]+)$#', (string) $r->cover_image, $m)) {
                // the cover as a file of ours when it was copied (link previews need the file), else its old address
                $cover = $images !== '' && is_file($images.'/'.$m[1]) ? 'blog/'.$m[1] : $assets.'/blog-img/'.$m[1];
                $picture('/blog-img/'.$m[1]);
            }
            $this->line(sprintf('%s  %s  (%d → %d characters%s)', $dry ? 'would import' : 'import', $r->slug, strlen((string) $r->content), strlen($body), $cover ? '' : ', no cover'));
            if ($dry) {
                continue;
            }
            $post = Post::firstOrNew(['legacy_id' => $r->id]);
            $post->fill([
                'slug' => (string) $r->slug,
                'format' => Post::FORMAT_HTML,
                'cover_path' => $cover,
                'author_name' => trim((string) $r->author) ?: null,
                'published_at' => $r->published_at ?: $r->created_at,
                // what was translated here later stays; the Czech text follows the old site
                'title' => ['cs' => trim((string) $r->title)] + (array) $post->title,
                'excerpt' => array_filter(['cs' => trim((string) $r->perex)] + (array) $post->excerpt),
                'body' => ['cs' => $body] + (array) $post->body,
            ])->save();
        }
        $this->info(($dry ? 'Would import ' : 'Imported ').count($rows).' articles, '.$copied.' pictures '.($dry ? 'to copy' : 'copied').'.');

        return self::SUCCESS;
    }
}
