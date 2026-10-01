<?php

namespace App\Engines\Import;

/** The public card of a model as its source shows it. */
final class ImportedModel
{
    /**
     * @param  list<string>  $images  addresses of pictures, the cover first
     * @param  list<string>  $tags
     * @param  list<string>  $files  names of the model files at the source (not the files themselves)
     */
    public function __construct(
        public readonly string $source,
        public readonly string $id,
        public readonly string $url,
        public readonly string $title,
        public readonly string $descriptionHtml,
        public readonly array $images,
        public readonly array $tags,
        public readonly string $license,
        public readonly bool $isRemix,
        public readonly ?string $remixSourceUrl,
        public readonly string $authorId,
        public readonly string $authorName,
        public readonly array $files = [],
        public readonly ?string $category = null,
    ) {}

    /** The description as plain text: paragraphs and list items kept as lines, markup and entities gone. */
    public function descriptionText(): string
    {
        $text = (string) preg_replace('/<\s*li[^>]*>/i', "\n• ", $this->descriptionHtml);
        $text = (string) preg_replace('/<\s*(p|div|br|h[1-6]|tr|hr)[^>]*\/?>/i', "\n", $text);
        $text = (string) preg_replace('/<\s*\/\s*(p|div|li|h[1-6]|tr)\s*>/i', "\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = (string) preg_replace('/ ?\n ?/', "\n", $text);

        return trim((string) preg_replace('/\n{3,}/', "\n\n", $text));
    }
}
