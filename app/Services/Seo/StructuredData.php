<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Support\PublicPages;
use Illuminate\Support\Str;

/**
 * Geração e validação do JSON-LD (schema.org). Só usa dados que existem no sistema:
 * nada de avaliações, estrelas, preços, ofertas ou autores inventados.
 */
class StructuredData
{
    /** Tipos que o site gera e as propriedades obrigatórias de cada um. */
    public const REQUIRED = [
        'Organization' => ['name', 'url'],
        'WebSite' => ['name', 'url'],
        'BreadcrumbList' => ['itemListElement'],
        'BlogPosting' => ['headline', 'datePublished', 'author', 'publisher', 'mainEntityOfPage'],
        'Article' => ['headline', 'datePublished', 'author'],
        'SoftwareApplication' => ['name', 'applicationCategory', 'operatingSystem'],
        'Service' => ['name', 'provider'],
        'ContactPage' => ['url'],
        'Blog' => ['name', 'url'],
        'AboutPage' => ['url'],
        'CollectionPage' => ['url'],
    ];

    /** Propriedades que só podem existir com dados reais e verificáveis (hoje o site não tem). */
    public const UNVERIFIED_PROPERTIES = ['aggregateRating', 'review', 'offers', 'price'];

    public function __construct(private SeoSettings $settings) {}

    public static function url(string $path = '/'): string
    {
        return app(SeoSettings::class)->url($path);
    }

    public static function organizationId(): string
    {
        return self::url('/').'#organization';
    }

    /**
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        $s = $this->settings;
        $node = [
            '@type' => 'Organization',
            '@id' => self::organizationId(),
            'name' => $s->get('company_name'),
            'legalName' => $s->get('organization_legal_name') ?: null,
            'url' => self::url('/'),
            'logo' => $s->absolute($s->get('logo_url')),
            'email' => $s->get('organization_email') ?: null,
            'telephone' => $s->get('organization_phone') ?: null,
            'areaServed' => 'BR',
            'description' => $s->get('company_description') ?: null,
            'sameAs' => array_values(array_filter((array) $s->get('social_profiles'))) ?: null,
        ];

        if (filled($s->get('organization_city'))) {
            $node['address'] = array_filter([
                '@type' => 'PostalAddress',
                'addressLocality' => $s->get('organization_city'),
                'addressRegion' => $s->get('organization_region') ?: null,
                'addressCountry' => $s->get('organization_country') ?: 'BR',
            ]);
        }

        return array_filter($node, fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => self::url('/').'#website',
            'name' => $this->settings->get('company_name'),
            'url' => self::url('/'),
            'inLanguage' => 'pt-BR',
            'publisher' => ['@id' => self::organizationId()],
        ];
    }

    /**
     * Trilha de navegação a partir da página inicial.
     *
     * @param  list<array{0: string, 1: string}>  $items  [nome, caminho]
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $items): array
    {
        $items = [['Início', '/'], ...$items];

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $item, int $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item[0],
                'item' => self::url($item[1]),
            ], $items, array_keys($items)),
        ];
    }

    /**
     * BlogPosting do artigo. O autor é o usuário que escreveu o artigo (dado real); a
     * imagem só entra quando o artigo tem capa.
     *
     * @return array<string, mixed>
     */
    public function blogPosting(BlogPost $post): array
    {
        $post->loadMissing(['author:id,name', 'category:id,name,slug']);
        $url = self::url('/blog/'.$post->slug);

        $article = array_filter([
            '@type' => 'BlogPosting',
            '@id' => $url.'#article',
            'headline' => Str::limit($post->title, 110, ''),
            'description' => $post->excerpt ?: null,
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => $post->updated_at?->toAtomString(),
            'author' => $post->author ? ['@type' => 'Person', 'name' => $post->author->name] : ['@id' => self::organizationId()],
            'publisher' => ['@id' => self::organizationId()],
            'mainEntityOfPage' => $url,
            'image' => $post->cover_image_url ? $this->settings->absolute($post->cover_image_url) : null,
            'articleSection' => $post->category?->name,
            'inLanguage' => 'pt-BR',
        ], fn ($value) => $value !== null);

        $trail = [['Blog', '/blog']];

        if ($post->category) {
            $trail[] = [$post->category->name, '/blog?category='.$post->category->slug];
        }

        $trail[] = [$post->title, '/blog/'.$post->slug];

        return ['@context' => 'https://schema.org', '@graph' => [$this->organization(), $article, self::breadcrumbs($trail)]];
    }

    /**
     * Valida a sintaxe e a adequação do JSON-LD gerado. Não substitui o Teste de
     * pesquisa aprimorada do Google: confere estrutura, tipos e propriedades obrigatórias.
     *
     * @return list<array{severity: string, message: string}>
     */
    public static function validate(mixed $jsonLd): array
    {
        if (! is_array($jsonLd) || $jsonLd === []) {
            return [['severity' => 'warning', 'message' => 'A página não tem dados estruturados.']];
        }

        $issues = [];
        $encoded = json_encode($jsonLd);

        if ($encoded === false || json_decode($encoded, true) === null) {
            return [['severity' => 'critical', 'message' => 'JSON-LD inválido (não pôde ser serializado).']];
        }

        if (($jsonLd['@context'] ?? null) !== 'https://schema.org') {
            $issues[] = ['severity' => 'critical', 'message' => '@context ausente ou diferente de https://schema.org.'];
        }

        $nodes = isset($jsonLd['@graph']) && is_array($jsonLd['@graph']) ? $jsonLd['@graph'] : [$jsonLd];

        foreach ($nodes as $node) {
            $type = is_array($node) ? ($node['@type'] ?? null) : null;

            if (! is_string($type)) {
                $issues[] = ['severity' => 'critical', 'message' => 'Há um nó sem @type.'];

                continue;
            }

            if (! isset(self::REQUIRED[$type])) {
                $issues[] = ['severity' => 'opportunity', 'message' => "Tipo {$type} não é validado pelo painel; confira no validador oficial."];

                continue;
            }

            foreach (self::REQUIRED[$type] as $property) {
                if (blank($node[$property] ?? null)) {
                    $issues[] = ['severity' => 'critical', 'message' => "{$type}: propriedade obrigatória \"{$property}\" ausente."];
                }
            }

            foreach (self::UNVERIFIED_PROPERTIES as $property) {
                if (array_key_exists($property, $node)) {
                    $issues[] = ['severity' => 'critical', 'message' => "{$type}: \"{$property}\" só pode ser publicado com dados reais e verificáveis."];
                }
            }

            if ($type === 'BreadcrumbList') {
                foreach ((array) ($node['itemListElement'] ?? []) as $position => $item) {
                    if (($item['position'] ?? null) !== $position + 1 || blank($item['name'] ?? null) || blank($item['item'] ?? null)) {
                        $issues[] = ['severity' => 'critical', 'message' => 'BreadcrumbList: item sem posição sequencial, nome ou URL.'];

                        break;
                    }
                }
            }

            if (in_array($type, ['BlogPosting', 'Article'], true)) {
                if (mb_strlen((string) ($node['headline'] ?? '')) > 110) {
                    $issues[] = ['severity' => 'warning', 'message' => "{$type}: headline com mais de 110 caracteres."];
                }

                if (blank($node['image'] ?? null)) {
                    $issues[] = ['severity' => 'opportunity', 'message' => "{$type}: sem imagem; uma capa ajuda a prévia nos resultados de busca."];
                }
            }

            if ($type === 'SoftwareApplication') {
                $issues[] = ['severity' => 'opportunity', 'message' => 'SoftwareApplication sem oferta e avaliação: válido para schema.org, mas não elegível ao resultado aprimorado do Google (sem dados reais de preço ou nota, é o esperado).'];
            }
        }

        return $issues;
    }

    /**
     * Tipos presentes no JSON-LD.
     *
     * @return list<mixed>
     */
    public static function types(mixed $jsonLd): array
    {
        if (! is_array($jsonLd)) {
            return [];
        }

        $nodes = isset($jsonLd['@graph']) && is_array($jsonLd['@graph']) ? $jsonLd['@graph'] : [$jsonLd];

        return array_values(array_filter(array_map(fn ($node) => is_array($node) ? ($node['@type'] ?? null) : null, $nodes)));
    }

    /**
     * JSON-LD de cada página institucional (chave do PageRegistry, sem o prefixo "page:"),
     * de acordo com o tipo real da página.
     *
     * @return array<string, mixed>
     */
    public function forPage(string $id): array
    {
        $graph = fn (array ...$nodes) => ['@context' => 'https://schema.org', '@graph' => [$this->organization(), ...$nodes]];

        if (str_starts_with($id, 'servico:')) {
            return PublicPages::serviceJsonLd(substr($id, 8));
        }

        if (str_starts_with($id, 'sistema:')) {
            return PublicPages::productJsonLd(substr($id, 8));
        }

        return match ($id) {
            'home' => $graph($this->website()),
            'sistemas' => $graph(...[...array_map(PublicPages::productNode(...), array_keys(PublicPages::PRODUCTS)), self::breadcrumbs([['Sistemas', '/sistemas']])]),
            'servicos' => $graph(self::breadcrumbs([['Soluções', '/servicos']])),
            'sobre' => $graph(['@type' => 'AboutPage', 'url' => self::url('/sobre'), 'about' => ['@id' => self::organizationId()]], self::breadcrumbs([['Sobre', '/sobre']])),
            'contato' => $graph(['@type' => 'ContactPage', 'url' => self::url('/contato')], self::breadcrumbs([['Contato', '/contato']])),
            'blog' => $graph(['@type' => 'Blog', 'name' => 'Blog da '.$this->settings->get('company_name'), 'url' => self::url('/blog'), 'publisher' => ['@id' => self::organizationId()]], self::breadcrumbs([['Blog', '/blog']])),
            default => [],
        };
    }
}
