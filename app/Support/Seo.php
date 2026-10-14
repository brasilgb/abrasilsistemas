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
            'description' => 'Empresa de tecnologia que desenvolve sistemas, aplicativos, sites, automações e integrações para empresas, além dos produtos VetorOS e VetorPet.',
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
