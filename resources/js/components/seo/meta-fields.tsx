import { X } from 'lucide-react';
import { useState } from 'react';
import type { KeyboardEvent } from 'react';
import { CharCount, FieldError, fieldClass } from '@/components/seo/indicators';
import { SerpPreview, SocialPreview } from '@/components/seo/serp-preview';

export type SeoMetaForm = {
    meta_title: string;
    meta_description: string;
    canonical_url: string;
    robots_index: boolean | null;
    robots_follow: boolean | null;
    og_title: string;
    og_description: string;
    og_image_url: string;
    og_image_alt: string;
    twitter_title: string;
    twitter_description: string;
    focus_keyword: string;
    secondary_keywords: string[];
    search_intent: string;
    related_pages: string[];
};

export type SeoMetaRecord = Partial<{
    [K in keyof SeoMetaForm]: SeoMetaForm[K] | null;
}> & { editorial_score?: number | null };

export type PageOption = { key: string; label: string; group: string };

export function seoMetaForm(meta?: SeoMetaRecord | null): SeoMetaForm {
    return {
        meta_title: meta?.meta_title ?? '',
        meta_description: meta?.meta_description ?? '',
        canonical_url: meta?.canonical_url ?? '',
        robots_index: meta?.robots_index ?? null,
        robots_follow: meta?.robots_follow ?? null,
        og_title: meta?.og_title ?? '',
        og_description: meta?.og_description ?? '',
        og_image_url: meta?.og_image_url ?? '',
        og_image_alt: meta?.og_image_alt ?? '',
        twitter_title: meta?.twitter_title ?? '',
        twitter_description: meta?.twitter_description ?? '',
        focus_keyword: meta?.focus_keyword ?? '',
        secondary_keywords: meta?.secondary_keywords ?? [],
        search_intent: meta?.search_intent ?? '',
        related_pages: meta?.related_pages ?? [],
    };
}

type Defaults = {
    title: string;
    description: string;
    canonical: string;
    robots: string;
    ogImage?: string | null;
};

/**
 * Campos de SEO por página (painel → Páginas e no formulário do artigo). Campo vazio
 * herda o padrão, mostrado como placeholder; noindex explícito nunca é sobrescrito.
 */
export function SeoMetaFields({
    data,
    setField,
    errors,
    defaults,
    siteName,
    intents,
    pageOptions,
    errorPrefix = '',
}: {
    data: SeoMetaForm;
    setField: <K extends keyof SeoMetaForm>(
        field: K,
        value: SeoMetaForm[K],
    ) => void;
    errors: Partial<Record<string, string>>;
    defaults: Defaults;
    siteName: string;
    intents: Record<string, string>;
    pageOptions: PageOption[];
    errorPrefix?: string;
}) {
    const err = (field: string) =>
        errors[`${errorPrefix}${field}`] as string | undefined;
    const title = data.meta_title || defaults.title;
    const description = data.meta_description || defaults.description;
    const defaultIndex = !defaults.robots.includes('noindex');
    const defaultFollow = !defaults.robots.includes('nofollow');

    return (
        <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div className="grid gap-5">
                <fieldset className="grid gap-4">
                    <legend className="mb-2 text-sm font-semibold">
                        Resultado de busca
                    </legend>
                    <label className="text-sm font-medium">
                        Meta title
                        <input
                            className={fieldClass}
                            value={data.meta_title}
                            placeholder={defaults.title}
                            maxLength={255}
                            onChange={(e) =>
                                setField('meta_title', e.target.value)
                            }
                        />
                        <span className="mt-1 flex justify-between gap-2">
                            <span className="text-xs font-normal text-muted-foreground">
                                Vazio usa o padrão. Título completo, como deve
                                aparecer na busca.
                            </span>
                            <CharCount value={title} min={30} max={65} />
                        </span>
                        <FieldError text={err('meta_title')} />
                    </label>
                    <label className="text-sm font-medium">
                        Meta description
                        <textarea
                            className={fieldClass}
                            rows={3}
                            value={data.meta_description}
                            placeholder={defaults.description}
                            maxLength={500}
                            onChange={(e) =>
                                setField('meta_description', e.target.value)
                            }
                        />
                        <span className="mt-1 flex justify-end">
                            <CharCount value={description} min={70} max={160} />
                        </span>
                        <FieldError text={err('meta_description')} />
                    </label>
                    <label className="text-sm font-medium">
                        URL canônica
                        <input
                            className={fieldClass}
                            type="url"
                            value={data.canonical_url}
                            placeholder={defaults.canonical}
                            onChange={(e) =>
                                setField('canonical_url', e.target.value)
                            }
                        />
                        <span className="mt-1 block text-xs font-normal text-muted-foreground">
                            Só preencha se esta página repete o conteúdo de
                            outra. Uma canonical diferente tira a página do
                            sitemap.
                        </span>
                        <FieldError text={err('canonical_url')} />
                    </label>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TriState
                            label="Indexação"
                            value={data.robots_index}
                            onChange={(value) =>
                                setField('robots_index', value)
                            }
                            inherited={defaultIndex}
                            on="index (aparecer na busca)"
                            off="noindex (fora da busca)"
                        />
                        <TriState
                            label="Links da página"
                            value={data.robots_follow}
                            onChange={(value) =>
                                setField('robots_follow', value)
                            }
                            inherited={defaultFollow}
                            on="follow (seguir os links)"
                            off="nofollow (só com motivo claro)"
                        />
                    </div>
                </fieldset>

                <fieldset className="grid gap-4">
                    <legend className="mb-2 text-sm font-semibold">
                        Compartilhamento (Open Graph e Twitter)
                    </legend>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-medium">
                            Título social
                            <input
                                className={fieldClass}
                                value={data.og_title}
                                placeholder={title}
                                onChange={(e) =>
                                    setField('og_title', e.target.value)
                                }
                            />
                            <FieldError text={err('og_title')} />
                        </label>
                        <label className="text-sm font-medium">
                            Imagem social (URL ou /caminho)
                            <input
                                className={fieldClass}
                                value={data.og_image_url}
                                placeholder={defaults.ogImage ?? ''}
                                onChange={(e) =>
                                    setField('og_image_url', e.target.value)
                                }
                            />
                            <FieldError text={err('og_image_url')} />
                        </label>
                    </div>
                    <label className="text-sm font-medium">
                        Descrição social
                        <textarea
                            className={fieldClass}
                            rows={2}
                            value={data.og_description}
                            placeholder={description}
                            onChange={(e) =>
                                setField('og_description', e.target.value)
                            }
                        />
                        <FieldError text={err('og_description')} />
                    </label>
                    <label className="text-sm font-medium">
                        Texto alternativo da imagem social
                        <input
                            className={fieldClass}
                            value={data.og_image_alt}
                            onChange={(e) =>
                                setField('og_image_alt', e.target.value)
                            }
                        />
                        <FieldError text={err('og_image_alt')} />
                    </label>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-medium">
                            Título no X/Twitter
                            <input
                                className={fieldClass}
                                value={data.twitter_title}
                                placeholder={data.og_title || title}
                                onChange={(e) =>
                                    setField('twitter_title', e.target.value)
                                }
                            />
                        </label>
                        <label className="text-sm font-medium">
                            Descrição no X/Twitter
                            <input
                                className={fieldClass}
                                value={data.twitter_description}
                                placeholder={data.og_description || description}
                                onChange={(e) =>
                                    setField(
                                        'twitter_description',
                                        e.target.value,
                                    )
                                }
                            />
                        </label>
                    </div>
                </fieldset>

                <fieldset className="grid gap-4">
                    <legend className="mb-2 text-sm font-semibold">
                        Planejamento editorial
                    </legend>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="text-sm font-medium">
                            Palavra-chave principal
                            <input
                                className={fieldClass}
                                value={data.focus_keyword}
                                maxLength={120}
                                placeholder="ex.: automação de processos"
                                onChange={(e) =>
                                    setField('focus_keyword', e.target.value)
                                }
                            />
                            <FieldError text={err('focus_keyword')} />
                        </label>
                        <label className="text-sm font-medium">
                            Intenção de busca
                            <select
                                className={fieldClass}
                                value={data.search_intent}
                                onChange={(e) =>
                                    setField('search_intent', e.target.value)
                                }
                            >
                                <option value="">Não definida</option>
                                {Object.entries(intents).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                        </label>
                    </div>
                    <TagInput
                        label="Palavras-chave secundárias"
                        values={data.secondary_keywords}
                        onChange={(values) =>
                            setField('secondary_keywords', values)
                        }
                        max={10}
                    />
                    <label className="text-sm font-medium">
                        Páginas relacionadas (links internos)
                        <select
                            className={fieldClass}
                            value=""
                            onChange={(e) =>
                                e.target.value &&
                                !data.related_pages.includes(e.target.value) &&
                                setField('related_pages', [
                                    ...data.related_pages,
                                    e.target.value,
                                ])
                            }
                        >
                            <option value="">Adicionar página…</option>
                            {pageOptions.map((option) => (
                                <option key={option.key} value={option.key}>
                                    {option.group} · {option.label}
                                </option>
                            ))}
                        </select>
                        <span className="mt-2 flex flex-wrap gap-2">
                            {data.related_pages.map((key) => (
                                <Chip
                                    key={key}
                                    label={
                                        pageOptions.find(
                                            (option) => option.key === key,
                                        )?.label ?? key
                                    }
                                    onRemove={() =>
                                        setField(
                                            'related_pages',
                                            data.related_pages.filter(
                                                (item) => item !== key,
                                            ),
                                        )
                                    }
                                />
                            ))}
                        </span>
                    </label>
                </fieldset>
            </div>

            <aside className="space-y-4 lg:sticky lg:top-4 lg:self-start">
                <SerpPreview
                    title={title}
                    description={description}
                    url={data.canonical_url || defaults.canonical}
                    siteName={siteName}
                />
                <SocialPreview
                    title={data.og_title || title}
                    description={data.og_description || description}
                    image={data.og_image_url || defaults.ogImage}
                    url={defaults.canonical}
                />
            </aside>
        </div>
    );
}

function TriState({
    label,
    value,
    onChange,
    inherited,
    on,
    off,
}: {
    label: string;
    value: boolean | null;
    onChange: (value: boolean | null) => void;
    inherited: boolean;
    on: string;
    off: string;
}) {
    return (
        <label className="text-sm font-medium">
            {label}
            <select
                className={fieldClass}
                value={value === null ? '' : value ? '1' : '0'}
                onChange={(e) =>
                    onChange(
                        e.target.value === '' ? null : e.target.value === '1',
                    )
                }
            >
                <option value="">Padrão ({inherited ? on : off})</option>
                <option value="1">{on}</option>
                <option value="0">{off}</option>
            </select>
        </label>
    );
}

export function TagInput({
    label,
    values,
    onChange,
    max,
}: {
    label: string;
    values: string[];
    onChange: (values: string[]) => void;
    max: number;
}) {
    const [input, setInput] = useState('');
    const add = () => {
        const value = input.trim();

        if (
            value &&
            values.length < max &&
            !values.some((item) => item.toLowerCase() === value.toLowerCase())
        ) {
            onChange([...values, value]);
        }

        setInput('');
    };
    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            add();
        } else if (event.key === 'Backspace' && !input && values.length) {
            onChange(values.slice(0, -1));
        }
    };

    return (
        <div className="text-sm font-medium">
            {label}
            <div className={`${fieldClass} flex flex-wrap items-center gap-2`}>
                {values.map((value) => (
                    <Chip
                        key={value}
                        label={value}
                        onRemove={() =>
                            onChange(values.filter((item) => item !== value))
                        }
                    />
                ))}
                <input
                    value={input}
                    onChange={(e) => setInput(e.target.value)}
                    onKeyDown={onKeyDown}
                    onBlur={add}
                    aria-label={label}
                    placeholder={values.length ? '' : 'Digite e aperte Enter'}
                    className="min-w-32 flex-1 bg-transparent outline-none"
                />
            </div>
        </div>
    );
}

function Chip({ label, onRemove }: { label: string; onRemove: () => void }) {
    return (
        <span className="inline-flex items-center gap-1 rounded-full bg-primary/10 py-1 pr-1.5 pl-2.5 text-xs font-semibold text-primary">
            {label}
            <button
                type="button"
                onClick={onRemove}
                className="rounded-full p-0.5 hover:bg-primary/20"
                aria-label={`Remover ${label}`}
            >
                <X className="size-3" />
            </button>
        </span>
    );
}
