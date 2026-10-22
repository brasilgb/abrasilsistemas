<?php

namespace App\Services\Seo;

use App\Models\Setting;
use App\Support\PublicPages;
use Illuminate\Http\Request;

/**
 * Configurações globais de SEO, guardadas como um JSON (criptografado) na tabela
 * `settings`. Tudo tem um padrão, então o site funciona igual antes de alguém
 * salvar o formulário. Os tokens do Search Console ficam numa chave separada
 * (Setting::SEO_GSC_TOKEN) e nunca passam por aqui.
 */
class SeoSettings
{
    /** Modelos de título aceitos: %title% é o título da página e %site% o nome do site. */
    public const TITLE_PLACEHOLDERS = ['%title%', '%site%'];

    public const AUDIT_SCHEDULES = ['off', 'daily', 'weekly'];

    /** Eventos comerciais do GA4 que o site sabe disparar (ver resources/js/lib/analytics.ts). */
    public const GA4_EVENTS = [
        'generate_lead' => 'Solicitação de orçamento (formulário de contato enviado)',
        'contact_whatsapp' => 'Início de atendimento pelo WhatsApp',
        'form_submit' => 'Envio de qualquer formulário público',
    ];

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'company_name' => 'ABrasil Sistemas',
            'company_description' => 'Empresa brasileira de tecnologia: produtos de software próprios, sistemas sob medida, aplicativos, sites, automação de processos e integração de sistemas.',
            'base_url' => '',
            'default_title' => 'ABrasil Sistemas — Software, sistemas e integração para empresas',
            'title_template' => '%title% | %site%',
            'default_description' => 'Empresa de tecnologia que desenvolve sistemas, aplicativos, sites, automações e integrações para empresas, além dos produtos VetorOS e VetorPet.',
            'logo_url' => '/images/logo_ab-512.png',
            'social_image_url' => '/images/site/heroes/home-og.jpg',
            'social_image_alt' => PublicPages::HERO_IMAGES['home'],
            'organization_legal_name' => '',
            'organization_email' => (string) config('contact.email'),
            'organization_phone' => '+'.config('contact.whatsapp_number'),
            'organization_city' => '',
            'organization_region' => '',
            'organization_country' => 'BR',
            'social_profiles' => [],
            'block_indexing' => false,
            'robots_disallow' => [],
            'sitemap_include_blog' => true,
            'sitemap_include_categories' => true,
            'google_verification' => '',
            'bing_verification' => '',
            'ga4_measurement_id' => '',
            'ga4_property_id' => '',
            'ga4_events' => array_keys(self::GA4_EVENTS),
            'gsc_property' => '',
            'indexnow_enabled' => false,
            'indexnow_key' => '',
            'audit_schedule' => 'weekly',
            'audit_max_pages' => 300,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $stored = json_decode((string) Setting::valueFor(Setting::SEO_SETTINGS, '[]'), true);

        return $this->cache = array_replace(self::defaults(), is_array($stored) ? array_intersect_key($stored, self::defaults()) : []);
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array{from: mixed, to: mixed}> o que mudou (para o registro de alterações)
     */
    public function save(array $values): array
    {
        $current = $this->all();
        $next = array_replace($current, array_intersect_key($values, self::defaults()));
        $changes = [];

        foreach ($next as $key => $value) {
            if ($current[$key] !== $value) {
                $changes[$key] = ['from' => $current[$key], 'to' => $value];
            }
        }

        Setting::query()->updateOrCreate(['key' => Setting::SEO_SETTINGS], ['value' => json_encode($next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        $this->cache = $next;

        return $changes;
    }

    /**
     * URL base canônica, sem barra final. Vem da configuração de SEO ou, sem ela, do APP_URL.
     */
    public function baseUrl(): string
    {
        $base = trim((string) $this->get('base_url')) ?: (string) config('app.url');

        return rtrim($base, '/');
    }

    public function canonicalHost(): string
    {
        return strtolower((string) parse_url($this->baseUrl(), PHP_URL_HOST));
    }

    /** Monta a URL canônica de um caminho interno ("/blog" → "https://dominio/blog"). */
    public function url(string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');

        // Igual ao url('/') do Laravel: a raiz sai sem barra final (é o canonical histórico da home).
        return $path === '/' ? $this->baseUrl() : $this->baseUrl().$path;
    }

    /** Converte uma URL relativa (ex.: /images/x.jpg) em absoluta no domínio canônico. */
    public function absolute(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return preg_match('#^https?://#i', $url) ? $url : $this->url($url);
    }

    /**
     * Indexação liberada para esta requisição? Bloqueia quando a opção global está ligada,
     * quando SEO_BLOCK_INDEXING=true (homologação) ou quando o site é acessado por um
     * host diferente do domínio canônico (ex.: endereço interno do container).
     */
    public function indexingAllowed(?Request $request = null): bool
    {
        if ($this->get('block_indexing') || config('seo.block_indexing')) {
            return false;
        }

        $request ??= request();
        $host = strtolower($request->getHost());

        return $host === $this->canonicalHost() || $host === 'www.'.$this->canonicalHost();
    }

    public function formatTitle(string $title): string
    {
        $site = (string) $this->get('company_name');

        if ($title === '' || str_contains($title, $site)) {
            return $title ?: (string) $this->get('default_title');
        }

        return str_replace(['%title%', '%site%'], [$title, $site], (string) $this->get('title_template'));
    }

    /**
     * Eventos do GA4 habilitados (lista de nomes).
     *
     * @return list<string>
     */
    public function ga4Events(): array
    {
        return array_values(array_intersect(array_keys(self::GA4_EVENTS), (array) $this->get('ga4_events')));
    }
}
