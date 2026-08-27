<?php

namespace App\Support;

class Seo
{
    /**
     * Build the SEO tag set for a page, filling in site-wide defaults.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function tags(array $overrides = []): array
    {
        $defaults = [
            'title' => config('app.name'),
            'description' => 'A ABrasil Sistemas desenvolve o VetorOS, o VetorPet, sistemas sob medida e sites profissionais para empresas.',
            'canonical' => url('/'),
            'robots' => 'index, follow, max-image-preview:large',
            'ogType' => 'website',
            'ogImage' => url('/images/dashboard-vetoros.webp'),
            'siteName' => 'ABrasil Sistemas',
            'locale' => 'pt_BR',
        ];

        $tags = array_merge($defaults, $overrides);
        $tags['ogTitle'] ??= $tags['title'];
        $tags['ogDescription'] ??= $tags['description'];

        return $tags;
    }
}
