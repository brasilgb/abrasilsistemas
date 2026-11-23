<?php

namespace App\Services;

use App\Services\Seo\SeoSettings;

/**
 * HTML inicial das páginas públicas, gerado no build (scripts/prerender.mjs), para que textos e links
 * existam sem JavaScript. O React substitui esse HTML ao carregar; não há Node em execução no servidor.
 * Sem o arquivo da página (ou com SEO_PRERENDER=false), o site funciona como antes.
 */
class Prerender
{
    public function __construct(private SeoSettings $settings, private CompanyProfile $company) {}

    /**
     * @param  array<string, mixed>  $page  página do Inertia
     */
    public function html(array $page): ?string
    {
        $component = (string) ($page['component'] ?? '');

        // Só visitantes: com login, o cabeçalho e os comentários do blog são diferentes.
        if (! config('seo.prerender') || ($page['props']['auth']['user'] ?? null) !== null) {
            return null;
        }

        return match ($component) {
            'blog/index' => $this->load('blog', 'blog-shell', fn () => view('prerender.blog-index', $page['props'])->render()),
            'blog/show' => $this->load('blog', 'blog-shell', fn () => view('prerender.blog-post', $page['props'])->render()),
            default => $this->load($this->name((string) ($page['url'] ?? '')), $component),
        };
    }

    private function name(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return $path === '' ? 'home' : str_replace('/', '__', $path);
    }

    /** @param  (callable(): string)|null  $content  conteúdo do banco, já em HTML seguro */
    private function load(string $name, string $component, ?callable $content = null): ?string
    {
        $file = config('seo.prerender_path').'/'.$name.'.json';

        if (preg_match('/^[a-z0-9_-]+$/', $name) !== 1 || ! is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        // O componente confere o arquivo com a página: um endereço igual com outra tela (404) não o usa.
        if (! is_array($data) || ($data['component'] ?? null) !== $component || ! is_string($data['html'] ?? null)) {
            return null;
        }

        return strtr($data['html'], [
            '__AB_WHATSAPP_DISPLAY__' => e(config('contact.whatsapp_display')),
            '__AB_WHATSAPP__' => e(config('contact.whatsapp_number')),
            '__AB_EMAIL__' => e($this->settings->get('organization_email') ?: config('contact.email')),
            '__AB_COMPANY__' => e($this->company->toArray()['summary']),
            '__AB_CONTENT__' => $content === null ? '' : $content(),
        ]);
    }
}
