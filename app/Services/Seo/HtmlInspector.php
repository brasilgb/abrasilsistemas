<?php

namespace App\Services\Seo;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Extrai do HTML de uma página o que a auditoria avalia: título, metatags, canonical,
 * H1, imagens sem alt, links e blocos JSON-LD.
 */
class HtmlInspector
{
    /**
     * @return array{title: string|null, description: string|null, robots: string|null, canonical: string|null, canonicalCount: int, descriptionCount: int, ogImage: string|null, h1Count: int, imagesWithoutAlt: int, links: list<string>, jsonLd: list<mixed>, jsonLdErrors: int, hasRenderedContent: bool}
     */
    public function inspect(string $html, string $pageUrl): array
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);

        $meta = function (string $query) use ($xpath): array {
            $values = [];

            foreach ($xpath->query($query) ?: [] as $node) {
                /** @var DOMElement $node */
                $values[] = trim($node->getAttribute('content') ?: $node->getAttribute('href'));
            }

            return $values;
        };

        $descriptions = $meta('//meta[@name="description"]');
        $canonicals = $meta('//link[@rel="canonical"]');
        $robots = $meta('//meta[@name="robots"]');
        $titles = $xpath->query('//title');
        $title = $titles === false ? null : $titles->item(0)?->textContent;

        $imagesWithoutAlt = 0;

        foreach ($doc->getElementsByTagName('img') as $image) {
            /** @var DOMElement $image */
            // alt="" é válido para imagem decorativa; só a ausência do atributo é problema.
            if (! $image->hasAttribute('alt')) {
                $imagesWithoutAlt++;
            }
        }

        $links = [];

        foreach ($doc->getElementsByTagName('a') as $anchor) {
            /** @var DOMElement $anchor */
            $href = trim($anchor->getAttribute('href'));

            if ($href !== '' && ! str_starts_with($href, '#') && ! preg_match('#^(mailto|tel|javascript|data):#i', $href)) {
                $links[] = self::resolve($href, $pageUrl);
            }
        }

        $jsonLd = [];
        $jsonLdErrors = 0;

        foreach ($xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $decoded = json_decode((string) $script->textContent, true);
            $decoded === null ? $jsonLdErrors++ : $jsonLd[] = $decoded;
        }

        // Sem SSR, o #app chega vazio e o conteúdo só existe depois do JavaScript.
        $app = $doc->getElementById('app');
        $hasRenderedContent = $app === null || trim($app->textContent) !== '' || $app->childNodes->length > 0;

        return [
            'title' => $title !== null ? trim($title) : null,
            'description' => $descriptions[0] ?? null,
            'robots' => $robots[0] ?? null,
            'canonical' => $canonicals[0] ?? null,
            'canonicalCount' => count($canonicals),
            'descriptionCount' => count($descriptions),
            'ogImage' => $meta('//meta[@property="og:image"]')[0] ?? null,
            'h1Count' => $doc->getElementsByTagName('h1')->length,
            'imagesWithoutAlt' => $imagesWithoutAlt,
            'links' => array_values(array_unique($links)),
            'jsonLd' => $jsonLd,
            'jsonLdErrors' => $jsonLdErrors,
            'hasRenderedContent' => $hasRenderedContent,
        ];
    }

    /** Resolve um href relativo à URL da página e remove o fragmento. */
    public static function resolve(string $href, string $base): string
    {
        $href = (string) preg_replace('/#.*$/', '', $href);

        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($href, '//')) {
            return ($parts['scheme'] ?? 'https').':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $dir = rtrim(dirname($parts['path'] ?? '/'), '/');

        return $origin.$dir.'/'.$href;
    }
}
