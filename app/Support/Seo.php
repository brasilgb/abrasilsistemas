<?php

namespace App\Support;

use App\Models\BlogPost;
use App\Models\SeoMeta;
use App\Services\Seo\PageRegistry;
use App\Services\Seo\SeoSettings;
use App\Services\Seo\StructuredData;

/**
 * Monta as tags de SEO de cada página (renderizadas no HTML inicial por app.blade.php e
 * mantidas pelo componente SeoHead). Ordem de herança, do mais forte ao mais fraco:
 * metadados da página (SeoMeta, painel) → padrão da página (código) → configurações
 * globais (SeoSettings). Um noindex explícito nunca é revertido pelos padrões.
 */
class Seo
{
    public const DEFAULT_ROBOTS = 'index, follow, max-image-preview:large';

    /**
     * Build the SEO tag set for a page, filling in site-wide defaults.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function tags(array $overrides = []): array
    {
        $settings = app(SeoSettings::class);
        $overrides = array_filter($overrides, fn ($value) => $value !== null && $value !== '');

        $defaults = [
            'title' => $settings->get('default_title') ?: config('app.name'),
            'description' => $settings->get('default_description'),
            'canonical' => $settings->url('/'),
            'robots' => self::DEFAULT_ROBOTS,
            'ogType' => 'website',
            'ogImage' => $settings->get('social_image_url'),
            'siteName' => $settings->get('company_name'),
            'locale' => 'pt_BR',
        ];

        $tags = array_merge($defaults, $overrides);
        $tags['canonical'] = self::canonicalize((string) $tags['canonical']);
        $tags['ogImage'] = $settings->absolute($tags['ogImage']);
        $tags['ogTitle'] ??= $tags['title'];
        $tags['ogDescription'] ??= $tags['description'];

        // O alt padrão só descreve a imagem padrão; uma imagem própria sem alt fica sem ele.
        if (! isset($overrides['ogImage'])) {
            $tags['ogImageAlt'] ??= $settings->get('social_image_alt') ?: null;
        }

        if (! isset($tags['ogImageAlt'])) {
            unset($tags['ogImageAlt']);
        }

        // Homologação, host não canônico ou bloqueio global: nada é indexável.
        if (! $settings->indexingAllowed()) {
            $tags['robots'] = 'noindex, nofollow';
        }

        return $tags;
    }

    /**
     * SEO de uma página institucional do PageRegistry ("home", "servico:slug", "sistema:slug"),
     * com os metadados editados no painel aplicados por cima.
     *
     * @param  array<string, mixed>  $overrides  dados que o painel não edita (jsonLd, ogType…)
     * @return array<string, mixed>
     */
    public static function page(string $id, array $overrides = []): array
    {
        $entry = app(PageRegistry::class)->find(SeoMeta::subjectKey(SeoMeta::TYPE_PAGE, $id));
        $base = $entry === null ? [] : [
            'title' => $entry['title'],
            'description' => $entry['description'],
            'canonical' => $entry['path'],
            'robots' => $entry['robots'],
            'ogImage' => $entry['ogImage'],
            'ogImageAlt' => $entry['ogImageAlt'],
        ];

        return self::tags(self::applyMeta([...$base, ...$overrides], SeoMeta::for(SeoMeta::TYPE_PAGE, $id)));
    }

    /**
     * @return array<string, mixed>
     */
    public static function post(BlogPost $post): array
    {
        $settings = app(SeoSettings::class);

        return self::tags(self::applyMeta([
            'title' => $settings->formatTitle($post->title),
            'description' => $post->excerpt,
            'canonical' => '/blog/'.$post->slug,
            'ogType' => 'article',
            'ogTitle' => $post->title,
            'ogDescription' => $post->excerpt,
            'ogImage' => $post->cover_image_url ?: $settings->get('social_image_url'),
            'jsonLd' => app(StructuredData::class)->blogPosting($post),
        ], SeoMeta::for(SeoMeta::TYPE_POST, $post->id)));
    }

    /**
     * Aplica os metadados do painel sobre os padrões da página. Campos vazios herdam.
     *
     * @param  array<string, mixed>  $tags
     * @return array<string, mixed>
     */
    public static function applyMeta(array $tags, ?SeoMeta $meta): array
    {
        if ($meta === null) {
            return $tags;
        }

        $customTitle = filled($meta->meta_title);

        if ($customTitle) {
            $tags['title'] = $meta->meta_title;
        }

        if (filled($meta->meta_description)) {
            $tags['description'] = $meta->meta_description;
        }

        $tags['ogTitle'] = $meta->og_title ?: ($customTitle ? $meta->meta_title : ($tags['ogTitle'] ?? null));
        $tags['ogDescription'] = $meta->og_description ?: ($meta->meta_description ?: ($tags['ogDescription'] ?? null));

        if (filled($meta->canonical_url)) {
            $tags['canonical'] = $meta->canonical_url;
        }

        if (filled($meta->og_image_url)) {
            $tags['ogImage'] = $meta->og_image_url;
            $tags['ogImageAlt'] = $meta->og_image_alt ?: null;
        } elseif (filled($meta->og_image_alt)) {
            $tags['ogImageAlt'] = $meta->og_image_alt;
        }

        if (filled($meta->twitter_title)) {
            $tags['twitterTitle'] = $meta->twitter_title;
        }

        if (filled($meta->twitter_description)) {
            $tags['twitterDescription'] = $meta->twitter_description;
        }

        $tags['robots'] = self::robots((string) ($tags['robots'] ?? self::DEFAULT_ROBOTS), $meta->robots_index, $meta->robots_follow);

        return array_filter($tags, fn ($value) => $value !== null);
    }

    /**
     * Combina a diretiva padrão com as escolhas explícitas do painel (null = herdar).
     */
    public static function robots(string $base, ?bool $index, ?bool $follow): string
    {
        $directives = array_map('trim', explode(',', strtolower($base)));
        $index ??= ! in_array('noindex', $directives, true);
        $follow ??= ! in_array('nofollow', $directives, true);

        return implode(', ', array_filter([
            $index ? 'index' : 'noindex',
            $follow ? 'follow' : 'nofollow',
            $index ? 'max-image-preview:large' : null,
        ]));
    }

    public static function isIndexable(?string $robots): bool
    {
        return ! str_contains(strtolower((string) $robots), 'noindex');
    }

    /**
     * Canonical sempre no domínio canônico: um caminho vira URL absoluta e uma URL do host
     * atual (ex.: acesso por www ou pelo endereço interno) passa para o domínio configurado.
     */
    public static function canonicalize(string $url): string
    {
        $settings = app(SeoSettings::class);

        if ($url === '' || str_starts_with($url, '/')) {
            return $settings->url($url ?: '/');
        }

        $root = rtrim(url('/'), '/');

        if ($url === $root || str_starts_with($url, $root.'/') || str_starts_with($url, $root.'?')) {
            return $settings->url(substr($url, strlen($root)) ?: '/');
        }

        return $url;
    }
}
