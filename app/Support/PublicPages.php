<?php

namespace App\Support;

use App\Services\Seo\SitemapBuilder;
use App\Services\Seo\StructuredData;

/**
 * Catálogo das páginas institucionais do site: a fonte única dos slugs, do SEO
 * (title/description) e das entradas do sitemap. O conteúdo exibido em cada
 * página fica em resources/js/data/site.ts, indexado pelos mesmos slugs.
 */
class PublicPages
{
    /**
     * Páginas de serviço, renderizadas pelo componente `site/servico` (o SEO profissional tem
     * landing page própria, `site/seo`). `catalog` lista as modalidades contratáveis, sem preço.
     *
     * @var array<string, array{title: string, description: string, serviceType: string, catalog?: list<array{name: string, description: string}>}>
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
        self::SEO_SERVICE => [
            'title' => 'SEO técnico, performance e otimização de sites | ABrasil Sistemas',
            'description' => 'Auditoria, correções técnicas, dados estruturados, performance e acompanhamento orgânico para sites e aplicações web que já estão no ar. Sem promessa de posição.',
            'serviceType' => 'SEO técnico e otimização de sites',
            'catalog' => [
                ['name' => 'SEO Essencial', 'description' => 'Auditoria e implantação técnica para empresas que já possuem um site.'],
                ['name' => 'SEO Profissional', 'description' => 'Auditoria aprofundada, correções técnicas, dados estruturados, performance, otimização editorial e melhorias de indexabilidade.'],
                ['name' => 'SEO Gerenciado', 'description' => 'Acompanhamento recorrente do desempenho orgânico, com recomendações, relatórios e melhorias contínuas.'],
            ],
        ],
    ];

    /** Página do serviço de SEO profissional (landing page própria). */
    public const SEO_SERVICE = 'seo-tecnico-e-otimizacao-de-sites';

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
     * Fotos dos heroes (public/images/site/heroes/{chave}-*). Aqui ficam só o texto
     * alternativo e o og:image; as variantes responsivas são montadas em
     * resources/js/data/hero-images.ts, e a origem e a licença, em docs/imagens-do-site.md.
     *
     * @var array<string, string>
     */
    public const HERO_IMAGES = [
        'home' => 'Equipe de desenvolvimento conversando ao lado de estações de trabalho com código na tela, em um escritório de tecnologia',
        'sistemas' => 'Pessoa usando um sistema de gestão em um notebook sobre a mesa de trabalho',
        'sistemas-sob-medida' => 'Duas pessoas desenvolvendo software com código-fonte aberto nos monitores, em um escritório claro',
        'automacao' => 'Profissional registrando uma operação no notebook em um depósito com paletes e balança',
        'integracoes' => 'Cabos de rede organizados e conectados a um painel de infraestrutura',
        'aplicativos' => 'Mão segurando um smartphone com um aplicativo de painel de dados, diante de um computador',
        'sites' => 'Pessoas trabalhando juntas no site de uma empresa aberto em um notebook',
        'landing-pages' => 'Notebook exibindo um painel de métricas de acesso e conversão',
        'vetoros' => 'Técnicos consertando equipamentos eletrônicos na bancada de uma assistência técnica',
        'vetorpet' => 'Cão deitado ao lado de um pote de ração em um ambiente doméstico',
        'sobre' => 'Dois colegas analisando juntos um projeto no notebook, com diagramas no quadro ao fundo',
        'contato' => 'Profissional conversando ao telefone com um cliente em frente ao notebook',
        'seo' => 'Notebook exibindo gráficos de desempenho de um site, com tempo de carregamento e taxa de rejeição',
    ];

    /** Foto do hero de cada página de serviço. */
    public const SERVICE_HERO_IMAGES = [
        'desenvolvimento-de-sistemas' => 'sistemas-sob-medida',
        'desenvolvimento-de-sites-para-empresas' => 'sites',
        'criacao-de-landing-pages' => 'landing-pages',
        'desenvolvimento-de-aplicativos' => 'aplicativos',
        'automacao-de-processos' => 'automacao',
        'integracao-de-sistemas-e-apis' => 'integracoes',
        self::SEO_SERVICE => 'seo',
    ];

    /**
     * og:image (JPG 1200×630, aceito por todas as redes) e o texto alternativo da foto do hero.
     *
     * @return array{ogImage: string, ogImageAlt: string}
     */
    public static function heroSeo(string $key): array
    {
        return [
            'ogImage' => url("/images/site/heroes/{$key}-og.jpg"),
            'ogImageAlt' => self::HERO_IMAGES[$key],
        ];
    }

    /**
     * Demais páginas públicas fixas, com o SEO padrão de cada uma. A chave é a mesma
     * usada pelo SEO por página (seo_metas.subject_id) e pelo PageRegistry.
     *
     * @var array<string, array{path: string, label: string, group: string, title: string, description: string, hero?: string, robots?: string, changefreq: string, priority: string}>
     */
    public const PAGES = [
        'home' => [
            'path' => '/', 'label' => 'Página inicial', 'group' => 'Institucional', 'hero' => 'home',
            'title' => 'ABrasil Sistemas — Software, sistemas e integração para empresas',
            'description' => 'Empresa de tecnologia que desenvolve sistemas sob medida, aplicativos, sites, automações e integrações para empresas, além dos produtos VetorOS e VetorPet.',
            'changefreq' => 'weekly', 'priority' => '1.0',
        ],
        'sistemas' => [
            'path' => '/sistemas', 'label' => 'Sistemas (produtos)', 'group' => 'Produtos', 'hero' => 'sistemas',
            'title' => 'Sistemas prontos para empresas — VetorOS e VetorPet | ABrasil Sistemas',
            'description' => 'Produtos de software da ABrasil Sistemas em operação: VetorOS para assistências técnicas e VetorPet para a gestão comercial no mercado pet.',
            'changefreq' => 'monthly', 'priority' => '0.9',
        ],
        'servicos' => [
            'path' => '/servicos', 'label' => 'Soluções (serviços)', 'group' => 'Serviços',
            'title' => 'Soluções — sistemas, aplicativos, sites, automação e integrações | ABrasil Sistemas',
            'description' => 'Desenvolvimento de sistemas sob medida, aplicativos, sites, landing pages, automação de processos e integração de sistemas para empresas.',
            'changefreq' => 'monthly', 'priority' => '0.8',
        ],
        'sobre' => [
            'path' => '/sobre', 'label' => 'Sobre', 'group' => 'Institucional', 'hero' => 'sobre',
            'title' => 'Sobre a ABrasil Sistemas — empresa de tecnologia e software',
            'description' => 'Conheça a ABrasil Sistemas: como trabalhamos, o que desenvolvemos e por que acompanhamos cada sistema depois que ele entra em operação.',
            'changefreq' => 'monthly', 'priority' => '0.6',
        ],
        'contato' => [
            'path' => '/contato', 'label' => 'Contato', 'group' => 'Institucional', 'hero' => 'contato',
            'title' => 'Fale sobre o seu projeto | ABrasil Sistemas',
            'description' => 'Conte o que sua empresa precisa: sistema pronto ou sob medida, site, landing page, aplicativo, automação ou integração. Resposta direta da equipe.',
            'changefreq' => 'monthly', 'priority' => '0.6',
        ],
        'blog' => [
            'path' => '/blog', 'label' => 'Blog', 'group' => 'Blog',
            'title' => 'Blog sobre tecnologia e gestão | ABrasil Sistemas',
            'description' => 'Informação prática para usar a tecnologia a favor da sua empresa.',
            'changefreq' => 'weekly', 'priority' => '0.7',
        ],
        'privacidade' => [
            'path' => '/politica-de-privacidade', 'label' => 'Política de Privacidade', 'group' => 'Legal', 'robots' => 'noindex, follow',
            'title' => 'Política de Privacidade | ABrasil Sistemas',
            'description' => 'Como a ABrasil Sistemas coleta, usa e protege dados pessoais no site e nos formulários de contato.',
            'changefreq' => 'yearly', 'priority' => '0.2',
        ],
        'termos' => [
            'path' => '/termos-de-uso', 'label' => 'Termos de Uso', 'group' => 'Legal', 'robots' => 'noindex, follow',
            'title' => 'Termos de Uso | ABrasil Sistemas',
            'description' => 'Termos de uso do site da ABrasil Sistemas.',
            'changefreq' => 'yearly', 'priority' => '0.2',
        ],
        'cookies' => [
            'path' => '/politica-de-cookies', 'label' => 'Política de Cookies', 'group' => 'Legal', 'robots' => 'noindex, follow',
            'title' => 'Política de Cookies | ABrasil Sistemas',
            'description' => 'Como a ABrasil Sistemas usa cookies no site.',
            'changefreq' => 'yearly', 'priority' => '0.2',
        ],
    ];

    /**
     * Dados da organização para schema.org, usados em todas as páginas públicas. Vêm das
     * configurações de SEO (painel → SEO → Configurações), só com dados reais da empresa.
     *
     * @return array<string, mixed>
     */
    public static function organization(): array
    {
        return app(StructuredData::class)->organization();
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
                    'url' => StructuredData::url('/'.$slug),
                    'areaServed' => 'BR',
                    'provider' => ['@id' => StructuredData::organizationId()],
                    ...isset($service['catalog']) ? ['hasOfferCatalog' => [
                        '@type' => 'OfferCatalog',
                        'name' => $service['serviceType'],
                        'itemListElement' => array_map(fn (array $item) => [
                            '@type' => 'Offer',
                            'itemOffered' => ['@type' => 'Service', ...$item],
                        ], $service['catalog']),
                    ]] : [],
                ],
                StructuredData::breadcrumbs([['Soluções', '/servicos'], [$service['serviceType'], '/'.$slug]]),
            ],
        ];
    }

    /**
     * SoftwareApplication só com o que é verificável: nome, categoria, sistema operacional e
     * descrição. Sem preço, oferta ou avaliação (não há dados reais publicados para isso).
     *
     * @return array<string, mixed>
     */
    public static function productJsonLd(string $slug): array
    {
        $product = self::PRODUCTS[$slug];

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                self::organization(),
                self::productNode($slug),
                StructuredData::breadcrumbs([['Sistemas', '/sistemas'], [$product['name'], '/sistemas/'.$slug]]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function productNode(string $slug): array
    {
        $product = self::PRODUCTS[$slug];

        return [
            '@type' => 'SoftwareApplication',
            'name' => $product['name'],
            'applicationCategory' => $product['category'],
            'operatingSystem' => $product['os'],
            'description' => $product['description'],
            'url' => StructuredData::url('/sistemas/'.$slug),
            'publisher' => ['@id' => StructuredData::organizationId()],
        ];
    }

    /**
     * URLs institucionais do sitemap: só as páginas canônicas e indexáveis (o blog é
     * acrescentado pelo SitemapBuilder).
     *
     * @return list<array{loc: string, changefreq: string, priority: string}>
     */
    public static function sitemapUrls(): array
    {
        return app(SitemapBuilder::class)->pageUrls();
    }
}
