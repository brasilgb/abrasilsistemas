<?php

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Allow-list HTML sanitizer for content produced by the rich text editor
 * (blog post bodies). Strips any tag or attribute that isn't explicitly
 * allowed — scripts, inline event handlers, styles, iframes, javascript:
 * URLs — while keeping the basic formatting markup the editor produces.
 */
class HtmlSanitizer
{
    /** Tag name => list of allowed attribute names. */
    private const ALLOWED_TAGS = [
        'p' => [], 'br' => [], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'h2' => [], 'h3' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'blockquote' => [],
        'a' => ['href', 'title', 'target'],
        'img' => ['src', 'alt', 'title'],
        'figure' => [], 'figcaption' => [],
    ];

    private const URL_ATTRIBUTES = ['href', 'src'];

    /** Tags whose content must be dropped entirely rather than unwrapped. */
    private const STRIP_ENTIRELY = ['script', 'style', 'iframe', 'object', 'embed', 'noscript'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>'.$html.'</body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $body = $dom->getElementsByTagName('body')->item(0);

        if (! $body) {
            return '';
        }

        self::cleanChildren($body);

        $result = '';
        foreach (iterator_to_array($body->childNodes) as $child) {
            $result .= $dom->saveHTML($child);
        }

        return trim($result);
    }

    private static function cleanChildren(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment) {
                $node->removeChild($child);

                continue;
            }

            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                $node->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::STRIP_ENTIRELY, true)) {
                $node->removeChild($child);

                continue;
            }

            if (! array_key_exists($tag, self::ALLOWED_TAGS)) {
                // Unwrap: drop the tag but keep its children in place.
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes ?? []) as $attr) {
                $name = strtolower($attr->name);

                if (! in_array($name, self::ALLOWED_TAGS[$tag], true) || (in_array($name, self::URL_ATTRIBUTES, true) && ! self::isSafeUrl($attr->value))) {
                    $child->removeAttribute($attr->name);
                }
            }

            if ($tag === 'a' && $child->getAttribute('href') !== '') {
                $child->setAttribute('rel', 'noopener noreferrer nofollow');
                $child->setAttribute('target', '_blank');
            }

            self::cleanChildren($child);
        }
    }

    private static function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        return (bool) preg_match('/^https?:\/\//i', $url);
    }
}
