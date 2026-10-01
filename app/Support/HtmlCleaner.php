<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Turns HTML somebody else wrote (an article of the old site, pasted with its own styles and classes) into HTML we
 * can print: only text markup survives, without styles, classes, scripts or handlers. Links leave with rel="nofollow
 * noopener" unless they stay on our site; pictures get the address the caller gives them.
 */
final class HtmlCleaner
{
    /** tag → attributes it may keep */
    private const KEEP = [
        'p' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'ul' => [], 'ol' => [], 'li' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [],
        'blockquote' => [], 'br' => [], 'hr' => [], 'code' => [], 'pre' => [], 'figure' => [], 'figcaption' => [], 'sup' => [], 'sub' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
        'a' => ['href'], 'img' => ['src', 'alt'],
    ];

    /** removed with everything inside */
    private const DROP = ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'noscript', 'link', 'meta', 'head', 'title'];

    /**
     * @param  callable(string): ?string|null  $image  address of a picture → the address to print, null = leave the picture out
     * @param  bool  $dropFirstHeading  the page prints the title itself: the article's own first <h1> goes
     */
    public static function clean(string $html, ?callable $image = null, bool $dropFirstHeading = true): string
    {
        if (trim($html) === '') {
            return '';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="clean-root">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $doc->getElementById('clean-root');
        if (! $root) {
            return e(strip_tags($html));
        }
        if ($dropFirstHeading && ($h1 = $root->getElementsByTagName('h1')->item(0))) {
            $h1->parentNode?->removeChild($h1);
        }
        self::walk($root, $image);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        // empty wrappers left behind by removed layout
        $out = (string) preg_replace('#<(p|h2|h3|h4|li|figure|blockquote)>\s*</\1>#u', '', $out);

        // the old layout's indentation: one line break between blocks is enough, none inside a block
        $out = (string) preg_replace('/[ \t]*\n\s*/u', "\n", $out);

        return trim((string) preg_replace('/>\n(?=[^<\n])/u', '>', $out));
    }

    private static function walk(DOMNode $node, ?callable $image): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP, true)) {
                $node->removeChild($child);

                continue;
            }
            self::walk($child, $image);
            // a further <h1> inside the text is a section heading here
            if ($tag === 'h1') {
                $child = self::rename($child, 'h2');
                $tag = 'h2';
            }
            if (! isset(self::KEEP[$tag])) {
                self::unwrap($child, in_array($tag, ['div', 'section', 'article', 'header', 'footer', 'main', 'aside'], true));

                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attribute) {
                if (! in_array(strtolower($attribute->name), self::KEEP[$tag], true)) {
                    $child->removeAttribute($attribute->name);
                }
            }
            if ($tag === 'a') {
                self::link($child);
            }
            if ($tag === 'img') {
                $src = $image ? $image((string) $child->getAttribute('src')) : (string) $child->getAttribute('src');
                if (! $src || ! preg_match('#^(https?://|/)#', $src)) {
                    $node->removeChild($child);

                    continue;
                }
                $child->setAttribute('src', $src);
                $child->setAttribute('loading', 'lazy');
                if (! $child->hasAttribute('alt')) {
                    $child->setAttribute('alt', '');
                }
            }
        }
    }

    private static function link(DOMElement $a): void
    {
        $href = trim((string) $a->getAttribute('href'));
        if ($href === '' || $href === '#' || ! preg_match('#^(https?://|/|\#|mailto:)#i', $href)) {
            self::unwrap($a, false);   // javascript: and friends: the words stay, the link goes

            return;
        }
        if (preg_match('#^https?://#i', $href) && ! preg_match('#^https?://([a-z0-9-]+\.)?matplace\.(com|cz)(/|$)#i', $href)) {
            $a->setAttribute('rel', 'nofollow noopener');
            $a->setAttribute('target', '_blank');
        }
    }

    /** The element goes, what it holds stays in its place (a layout block leaves a paragraph break behind). */
    private static function unwrap(DOMElement $element, bool $block): void
    {
        $parent = $element->parentNode;
        if (! $parent) {
            return;
        }
        // loose text of a layout block becomes a paragraph, so it does not run into its neighbours
        if ($block && self::onlyInline($element) && trim($element->textContent) !== '') {
            self::rename($element, 'p');

            return;
        }
        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }

    private static function onlyInline(DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && ! in_array(strtolower($child->tagName), ['a', 'strong', 'b', 'em', 'i', 'u', 'br', 'code', 'sup', 'sub', 'span', 'img'], true)) {
                return false;
            }
        }

        return true;
    }

    private static function rename(DOMElement $element, string $tag): DOMElement
    {
        $new = $element->ownerDocument->createElement($tag);
        while ($element->firstChild) {
            $new->appendChild($element->firstChild);
        }
        $element->parentNode?->replaceChild($new, $element);

        return $new;
    }
}
