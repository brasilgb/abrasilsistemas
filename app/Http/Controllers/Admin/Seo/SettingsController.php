<?php

namespace App\Http\Controllers\Admin\Seo;

use App\Http\Controllers\Controller;
use App\Services\Seo\RobotsBuilder;
use App\Services\Seo\SeoChangeLogger;
use App\Services\Seo\SeoSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configurações globais de SEO. Não há campo de HTML ou script livre: tudo é texto,
 * URL ou opção validada, e só administradores chegam aqui (middleware admin).
 */
class SettingsController extends Controller
{
    public function edit(SeoSettings $settings): Response
    {
        return Inertia::render('admin/seo/settings', [
            'settings' => collect($settings->all())->only(self::FIELDS)->all(),
            'appUrl' => config('app.url'),
            'privatePaths' => RobotsBuilder::PRIVATE_PATHS,
            'auditSchedules' => ['off' => 'Desligada', 'daily' => 'Diária (04:30)', 'weekly' => 'Semanal (segunda, 04:30)'],
            'blockedByEnvironment' => (bool) config('seo.block_indexing'),
        ]);
    }

    public function update(Request $request, SeoSettings $settings): RedirectResponse
    {
        $relativeOrUrl = fn ($attribute, $value, $fail) => $value === null || $value === '' || (str_starts_with((string) $value, '/') && ! str_starts_with((string) $value, '//')) || preg_match('#^https?://#i', (string) $value)
            ? null : $fail('Informe uma URL http(s) ou um caminho do site (/imagem.png).');

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'company_description' => ['nullable', 'string', 'max:500'],
            'base_url' => ['nullable', 'url:https,http', 'max:255', 'regex:#^https?://[^/?#]+/?$#'],
            'default_title' => ['required', 'string', 'max:120'],
            'title_template' => ['required', 'string', 'max:120', 'regex:/%title%/'],
            'default_description' => ['required', 'string', 'max:300'],
            'logo_url' => ['nullable', 'string', 'max:2048', $relativeOrUrl],
            'social_image_url' => ['nullable', 'string', 'max:2048', $relativeOrUrl],
            'social_image_alt' => ['nullable', 'string', 'max:255'],
            'organization_legal_name' => ['nullable', 'string', 'max:180'],
            'organization_email' => ['nullable', 'email', 'max:180'],
            'organization_phone' => ['nullable', 'string', 'max:40', 'regex:/^\+?[0-9 ()-]{8,40}$/'],
            'organization_city' => ['nullable', 'string', 'max:120'],
            'organization_region' => ['nullable', 'string', 'max:60'],
            'organization_country' => ['nullable', 'string', 'size:2'],
            'social_profiles' => ['nullable', 'array', 'max:10'],
            'social_profiles.*' => ['url:https', 'max:255'],
            'block_indexing' => ['boolean'],
            'robots_disallow' => ['nullable', 'array', 'max:50'],
            'robots_disallow.*' => ['string', function ($attribute, $value, $fail) {
                if (! RobotsBuilder::isValidPath((string) $value)) {
                    $fail('Caminho inválido para o robots.txt: comece com /, sem espaços. "Disallow: /" não é aceito; use "Bloquear indexação".');
                }
            }],
            'sitemap_include_blog' => ['boolean'],
            'sitemap_include_categories' => ['boolean'],
            'audit_schedule' => ['required', Rule::in(SeoSettings::AUDIT_SCHEDULES)],
            'audit_max_pages' => ['required', 'integer', 'min:10', 'max:'.config('seo.audit.max_pages_limit', 1000)],
        ]);

        $data['social_profiles'] = array_values(array_filter($data['social_profiles'] ?? []));
        $data['robots_disallow'] = array_values(array_unique(array_filter($data['robots_disallow'] ?? [])));
        $data['base_url'] = rtrim((string) ($data['base_url'] ?? ''), '/');
        $data['organization_country'] = strtoupper((string) ($data['organization_country'] ?? 'BR')) ?: 'BR';

        foreach (['company_description', 'logo_url', 'social_image_url', 'social_image_alt', 'organization_legal_name', 'organization_email', 'organization_phone', 'organization_city', 'organization_region'] as $field) {
            $data[$field] = trim(strip_tags((string) ($data[$field] ?? '')));
        }

        $changes = $settings->save($data);

        if ($changes !== []) {
            SeoChangeLogger::log('settings', null, 'updated', $changes, $request->user()->id);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $data['block_indexing'] ? 'Configurações salvas. A indexação do site está BLOQUEADA.' : 'Configurações salvas.']);

        return to_route('admin.seo.settings');
    }

    public const FIELDS = [
        'company_name', 'company_description', 'base_url', 'default_title', 'title_template', 'default_description',
        'logo_url', 'social_image_url', 'social_image_alt', 'organization_legal_name', 'organization_email',
        'organization_phone', 'organization_city', 'organization_region', 'organization_country', 'social_profiles',
        'block_indexing', 'robots_disallow', 'sitemap_include_blog', 'sitemap_include_categories', 'audit_schedule', 'audit_max_pages',
    ];
}
