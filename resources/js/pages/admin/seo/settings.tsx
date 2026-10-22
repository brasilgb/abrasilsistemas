import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import {
    CharCount,
    FieldError,
    Notice,
    fieldClass,
} from '@/components/seo/indicators';
import { TagInput } from '@/components/seo/meta-fields';
import { SeoPage } from '@/components/seo/seo-page';
import { Button } from '@/components/ui/button';

type Settings = {
    company_name: string;
    company_description: string;
    base_url: string;
    default_title: string;
    title_template: string;
    default_description: string;
    logo_url: string;
    social_image_url: string;
    social_image_alt: string;
    organization_legal_name: string;
    organization_email: string;
    organization_phone: string;
    organization_city: string;
    organization_region: string;
    organization_country: string;
    social_profiles: string[];
    block_indexing: boolean;
    robots_disallow: string[];
    sitemap_include_blog: boolean;
    sitemap_include_categories: boolean;
    audit_schedule: string;
    audit_max_pages: number;
};

type Props = {
    settings: Settings;
    appUrl: string;
    privatePaths: string[];
    auditSchedules: Record<string, string>;
    blockedByEnvironment: boolean;
};

function Section({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-4 rounded-lg border p-4 sm:p-5">
            <div>
                <h2 className="text-sm font-semibold">{title}</h2>
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

export default function SeoSettingsPage({
    settings,
    appUrl,
    privatePaths,
    auditSchedules,
    blockedByEnvironment,
}: Props) {
    const form = useForm<Settings>({
        ...settings,
        social_profiles: settings.social_profiles ?? [],
        robots_disallow: settings.robots_disallow ?? [],
    });
    const errors = form.errors as Record<string, string | undefined>;
    const arrayError = (field: string) =>
        errors[field] ??
        Object.entries(errors).find(([key]) =>
            key.startsWith(`${field}.`),
        )?.[1];
    const text = (field: keyof Settings, label: string, hint?: string) => (
        <label className="text-sm font-medium">
            {label}
            <input
                className={fieldClass}
                value={String(form.data[field] ?? '')}
                onChange={(e) => form.setData(field, e.target.value as never)}
            />
            {hint && (
                <span className="mt-1 block text-xs font-normal text-muted-foreground">
                    {hint}
                </span>
            )}
            <FieldError text={errors[field]} />
        </label>
    );
    const titlePreview = form.data.title_template
        .replace('%title%', 'Automação de processos')
        .replace('%site%', form.data.company_name);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put('/admin/seo/configuracoes', { preserveScroll: true });
    };

    return (
        <SeoPage
            title="Configurações"
            description="Valores padrão de todo o site. Cada página pode sobrescrever o que precisar em Páginas e metadados."
        >
            <form onSubmit={submit} className="grid max-w-4xl gap-6">
                <Section title="Empresa e endereço">
                    <div className="grid gap-4 sm:grid-cols-2">
                        {text('company_name', 'Nome da empresa (nome do site)')}
                        {text(
                            'base_url',
                            'URL base e domínio canônico',
                            `Vazio usa ${appUrl}. Só esquema e domínio, sem caminho.`,
                        )}
                    </div>
                    <label className="text-sm font-medium">
                        Descrição da empresa
                        <textarea
                            className={fieldClass}
                            rows={2}
                            maxLength={500}
                            value={form.data.company_description}
                            onChange={(e) =>
                                form.setData(
                                    'company_description',
                                    e.target.value,
                                )
                            }
                        />
                        <FieldError text={errors.company_description} />
                    </label>
                </Section>

                <Section
                    title="Títulos e descrição padrão"
                    description="Usados quando a página não define os seus."
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        {text(
                            'default_title',
                            'Título padrão (página inicial)',
                        )}
                        <label className="text-sm font-medium">
                            Modelo de título
                            <input
                                className={fieldClass}
                                value={form.data.title_template}
                                onChange={(e) =>
                                    form.setData(
                                        'title_template',
                                        e.target.value,
                                    )
                                }
                            />
                            <span className="mt-1 block text-xs font-normal text-muted-foreground">
                                %title% = título da página, %site% = nome da
                                empresa. Ex.: {titlePreview}
                            </span>
                            <FieldError text={errors.title_template} />
                        </label>
                    </div>
                    <label className="text-sm font-medium">
                        Descrição padrão
                        <textarea
                            className={fieldClass}
                            rows={2}
                            maxLength={300}
                            value={form.data.default_description}
                            onChange={(e) =>
                                form.setData(
                                    'default_description',
                                    e.target.value,
                                )
                            }
                        />
                        <span className="mt-1 flex justify-end">
                            <CharCount
                                value={form.data.default_description}
                                min={70}
                                max={160}
                            />
                        </span>
                        <FieldError text={errors.default_description} />
                    </label>
                </Section>

                <Section
                    title="Logotipo e imagem social"
                    description="URL http(s) ou caminho do site (/images/…). A imagem social ideal tem 1200×630 px."
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        {text('logo_url', 'Logotipo')}
                        {text('social_image_url', 'Imagem social padrão')}
                    </div>
                    {text(
                        'social_image_alt',
                        'Texto alternativo da imagem social',
                    )}
                </Section>

                <Section
                    title="Dados institucionais (Schema.org)"
                    description="Entram no JSON-LD Organization. Preencha só dados reais e públicos; campos vazios são omitidos."
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        {text('organization_legal_name', 'Razão social')}
                        {text('organization_email', 'E-mail público')}
                        {text(
                            'organization_phone',
                            'Telefone público',
                            'Ex.: +55 51 99893-1325',
                        )}
                        {text('organization_city', 'Cidade')}
                        {text('organization_region', 'Estado (UF)')}
                        {text(
                            'organization_country',
                            'País',
                            'Código de 2 letras (BR).',
                        )}
                    </div>
                    <div>
                        <TagInput
                            label="Perfis sociais oficiais (sameAs)"
                            values={form.data.social_profiles}
                            onChange={(values) =>
                                form.setData('social_profiles', values)
                            }
                            max={10}
                        />
                        <span className="mt-1 block text-xs text-muted-foreground">
                            URLs https completas, uma por vez (Enter para
                            adicionar).
                        </span>
                        <FieldError text={arrayError('social_profiles')} />
                    </div>
                </Section>

                <Section title="Robots e indexação">
                    {blockedByEnvironment && (
                        <Notice tone="danger">
                            Este ambiente bloqueia a indexação pelo .env
                            (SEO_BLOCK_INDEXING=true). A opção abaixo não libera
                            o bloqueio.
                        </Notice>
                    )}
                    <label className="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="mt-0.5"
                            checked={form.data.block_indexing}
                            onChange={(e) =>
                                form.setData('block_indexing', e.target.checked)
                            }
                        />
                        <span>
                            <span className="font-medium">
                                Bloquear indexação do site inteiro
                            </span>
                            <span className="block text-muted-foreground">
                                robots.txt com Disallow: /, noindex em todas as
                                páginas e sitemap vazio. Só para manutenção ou
                                homologação.
                            </span>
                        </span>
                    </label>
                    {form.data.block_indexing && !settings.block_indexing && (
                        <Notice tone="danger">
                            Ao salvar, o site sai dos buscadores até a opção ser
                            desmarcada.
                        </Notice>
                    )}
                    <div>
                        <TagInput
                            label="Caminhos extras no Disallow"
                            values={form.data.robots_disallow}
                            onChange={(values) =>
                                form.setData('robots_disallow', values)
                            }
                            max={50}
                        />
                        <span className="mt-1 block text-xs text-muted-foreground">
                            Comece com /. Já bloqueados sempre:{' '}
                            {privatePaths.join(' ')}
                        </span>
                        <FieldError text={arrayError('robots_disallow')} />
                    </div>
                </Section>

                <Section title="Sitemap e auditoria">
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.sitemap_include_blog}
                            onChange={(e) =>
                                form.setData(
                                    'sitemap_include_blog',
                                    e.target.checked,
                                )
                            }
                        />
                        Incluir artigos publicados no sitemap
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.sitemap_include_categories}
                            onChange={(e) =>
                                form.setData(
                                    'sitemap_include_categories',
                                    e.target.checked,
                                )
                            }
                        />
                        Incluir categorias do blog com artigos publicados
                    </label>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-medium">
                            Auditoria agendada
                            <select
                                className={fieldClass}
                                value={form.data.audit_schedule}
                                onChange={(e) =>
                                    form.setData(
                                        'audit_schedule',
                                        e.target.value,
                                    )
                                }
                            >
                                {Object.entries(auditSchedules).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                            <FieldError text={errors.audit_schedule} />
                        </label>
                        <label className="text-sm font-medium">
                            Máximo de páginas por auditoria
                            <input
                                type="number"
                                min={10}
                                className={fieldClass}
                                value={form.data.audit_max_pages}
                                onChange={(e) =>
                                    form.setData(
                                        'audit_max_pages',
                                        Number(e.target.value),
                                    )
                                }
                            />
                            <FieldError text={errors.audit_max_pages} />
                        </label>
                    </div>
                </Section>

                <div className="flex justify-end">
                    <Button disabled={form.processing}>
                        Salvar configurações
                    </Button>
                </div>
            </form>
        </SeoPage>
    );
}
