import { Link, useForm } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';
import type { FormEvent } from 'react';
import { Notice } from '@/components/seo/indicators';
import { SeoMetaFields, seoMetaForm } from '@/components/seo/meta-fields';
import type {
    PageOption,
    SeoMetaForm,
    SeoMetaRecord,
} from '@/components/seo/meta-fields';
import { SeoPage } from '@/components/seo/seo-page';
import { Button } from '@/components/ui/button';

type Props = {
    page: {
        key: string;
        label: string;
        group: string;
        path: string;
        url: string;
        effectiveRobots: string;
        effectiveCanonical: string;
        indexable: boolean;
    };
    meta: SeoMetaRecord | null;
    defaults: {
        title: string;
        description: string;
        robots: string;
        canonical: string;
        ogImage: string | null;
    };
    pageOptions: PageOption[];
    intents: Record<string, string>;
    siteName: string;
};

export default function SeoPageEdit({
    page,
    meta,
    defaults,
    pageOptions,
    intents,
    siteName,
}: Props) {
    const form = useForm<SeoMetaForm>(seoMetaForm(meta));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(`/admin/seo/paginas/${page.key}`, { preserveScroll: true });
    };

    return (
        <SeoPage
            title={page.label}
            description={`${page.group} · ${page.path}`}
            actions={
                <Button variant="outline" asChild>
                    <a href={page.url} target="_blank" rel="noreferrer">
                        <ExternalLink className="size-4" />
                        Ver página
                    </a>
                </Button>
            }
        >
            <Notice tone={page.indexable ? 'info' : 'warn'}>
                Hoje a página sai com <code>{page.effectiveRobots}</code> e
                canonical <code>{page.effectiveCanonical}</code>.{' '}
                {page.indexable
                    ? 'Ela entra no sitemap.'
                    : 'Ela não entra no sitemap.'}
            </Notice>
            <form onSubmit={submit} className="space-y-6">
                <SeoMetaFields
                    data={form.data}
                    setField={(field, value) =>
                        form.setData((data) => ({ ...data, [field]: value }))
                    }
                    errors={form.errors}
                    defaults={defaults}
                    siteName={siteName}
                    intents={intents}
                    pageOptions={pageOptions}
                />
                <div className="flex justify-end gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/admin/seo/paginas">Voltar</Link>
                    </Button>
                    <Button disabled={form.processing}>Salvar metadados</Button>
                </div>
            </form>
        </SeoPage>
    );
}
