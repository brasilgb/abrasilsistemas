<?php

namespace App\Support;

/**
 * Catálogo das páginas institucionais do site: a fonte única dos slugs, do SEO
 * (title/description) e das entradas do sitemap. O conteúdo exibido em cada
 * página fica em resources/js/data/site.ts, indexado pelos mesmos slugs.
 */
class PublicPages
{
    /**
     * Páginas de serviço, renderizadas pelo componente `site/servico`.
     *
     * @var array<string, array{title: string, description: string, serviceType: string}>
     */
    public const SERVICES = [
        'desenvolvimento-de-sistemas' => [
            'title' => 'Desenvolvimento de sistemas sob medida para empresas | ABrasil Sistemas',
            'description' => 'Software sob medida para substituir planilhas, controles manuais e sistemas que não conversam. Do diagnóstico à implantação e evolução.',
            'serviceType' => 'Desenvolvimento de software sob medida',
        ],
        'desenvolvimento-de-sites-para-empresas' => [
            'title' => 'Criação de sites profissionais para empresas | ABrasil Sistemas',
            'description' => 'Sites institucionais e empresariais rápidos, responsivos e com SEO técnico, integrados a formulários, CRM e ferramentas de análise.',
            'serviceType' => 'Criação de sites empresariais',
        ],
        'criacao-de-landing-pages' => [
            'title' => 'Criação de landing pages para campanhas e captação | ABrasil Sistemas',
            'description' => 'Landing pages rápidas e focadas em conversão para lançamentos, campanhas e captação de leads, integradas a CRM, WhatsApp e analytics.',
            'serviceType' => 'Criação de landing pages',
        ],
        'desenvolvimento-de-aplicativos' => [
            'title' => 'Desenvolvimento de aplicativos Android e iOS para empresas | ABrasil Sistemas',
            'description' => 'Aplicativos para clientes, equipes de campo, vendas e operações internas, integrados aos sistemas e dados que a empresa já usa.',
            'serviceType' => 'Desenvolvimento de aplicativos',
        ],
        'automacao-de-processos' => [
            'title' => 'Automação de processos empresariais | ABrasil Sistemas',
            'description' => 'Automação de rotinas, notificações, cobrança, follow-up e movimentação de dados entre sistemas para reduzir trabalho repetitivo e erros.',
            'serviceType' => 'Automação de processos',
        ],
        'integracao-de-sistemas-e-apis' => [
            'title' => 'Integração de sistemas e desenvolvimento de APIs | ABrasil Sistemas',
            'description' => 'Integração entre ERP, CRM, e-commerce, WhatsApp, gateways e sistemas próprios por APIs e webhooks, sem redigitação de dados.',
            'serviceType' => 'Integração de sistemas e APIs',
        ],
    ];

    /**
     * Produtos próprios com página em /sistemas/{slug}. Só entram aqui sistemas
     * que existem e estão em operação.
     *
     * @var array<string, array{name: string, title: string, description: string, os: string, category: string}>
     */
    public const PRODUCTS = [
        'vetoros' => [
            'name' => 'VetorOS',
            'title' => 'VetorOS — sistema para assistências técnicas | ABrasil Sistemas',
            'description' => 'Ordens de serviço, orçamentos, clientes, estoque, vendas e financeiro de assistências técnicas em um só sistema web.',
            'os' => 'Web',
            'category' => 'BusinessApplication',
        ],
        'vetorpet' => [
            'name' => 'VetorPet',
            'title' => 'VetorPet — gestão comercial para o mercado pet | ABrasil Sistemas',
            'description' => 'Carteira de clientes, catálogo, pedidos em campo, visitas e comissões para distribuidores e representantes do mercado pet.',
            'os' => 'Web, Android',
            'category' => 'BusinessApplication',
        ],
    ];

    /**
     * Dados da organização para schema.org, usados em todas as páginas públicas.
     *
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => 'ABrasil Sistemas',
            'url' => url('/'),
            'logo' => url('/images/logo_ab-512.png'),
            'email' => config('contact.email'),
            'telephone' => '+'.config('contact.whatsapp_number'),
            'areaServed' => 'BR',
            'description' => 'Empresa brasileira de tecnologia: produtos de software próprios, sistemas sob medida, aplicativos, sites, automação de processos e integração de sistemas.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function serviceJsonLd(string $slug): array
    {
        $service = self::SERVICES[$slug];

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::organization(),
                [
                    '@type' => 'Service',
                    'name' => $service['serviceType'],
                    'serviceType' => $service['serviceType'],
                    'description' => $service['description'],
                    'url' => url('/'.$slug),
                    'areaServed' => 'BR',
                    'provider' => ['@id' => url('/').'#organization'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function productJsonLd(string $slug): array
    {
        $product = self::PRODUCTS[$slug];

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::organization(),
                [
                    '@type' => 'SoftwareApplication',
                    'name' => $product['name'],
                    'applicationCategory' => $product['category'],
                    'operatingSystem' => $product['os'],
                    'description' => $product['description'],
                    'url' => url('/sistemas/'.$slug),
                    'publisher' => ['@id' => url('/').'#organization'],
                ],
            ],
        ];
    }

    /**
     * URLs institucionais do sitemap (o blog é acrescentado pelo SitemapController).
     *
     * @return list<array{loc: string, changefreq: string, priority: string}>
     */
    public static function sitemapUrls(): array
    {
        $urls = [
            ['loc' => url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => url('/sistemas'), 'changefreq' => 'monthly', 'priority' => '0.9'],
        ];

        foreach (array_keys(self::PRODUCTS) as $slug) {
            $urls[] = ['loc' => url('/sistemas/'.$slug), 'changefreq' => 'monthly', 'priority' => '0.8'];
        }

        $urls[] = ['loc' => url('/servicos'), 'changefreq' => 'monthly', 'priority' => '0.8'];

        foreach (array_keys(self::SERVICES) as $slug) {
            $urls[] = ['loc' => url('/'.$slug), 'changefreq' => 'monthly', 'priority' => '0.9'];
        }

        foreach (['/sobre' => '0.6', '/contato' => '0.6', '/blog' => '0.7'] as $path => $priority) {
            $urls[] = ['loc' => url($path), 'changefreq' => $path === '/blog' ? 'weekly' : 'monthly', 'priority' => $priority];
        }

        return $urls;
    }
}
