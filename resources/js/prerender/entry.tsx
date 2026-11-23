import { createInertiaApp } from '@inertiajs/react';
import { renderToString } from 'react-dom/server';
import { TooltipProvider } from '@/components/ui/tooltip';
import { products, services } from '@/data/site';

/**
 * Pré-renderização das páginas públicas, executada SÓ no build (scripts/prerender.mjs): gera o HTML
 * que App\Services\Prerender põe dentro de #app. Não há hidratação: ao carregar, o React monta a
 * página do zero e substitui esse HTML, então uma diferença entre os dois não quebra nada.
 *
 * Valores que mudam sem build (contatos e dados institucionais) saem como marcadores __AB_*__,
 * preenchidos pelo PHP a cada requisição. Conteúdo do banco (blog) entra por __AB_CONTENT__.
 */
const pages = import.meta.glob(
    [
        '../pages/welcome.tsx',
        '../pages/servicos.tsx',
        '../pages/sobre.tsx',
        '../pages/contato.tsx',
        '../pages/site/*.tsx',
        '../pages/legal/*.tsx',
        './blog-shell.tsx',
    ],
    { eager: true },
);

const sharedProps = {
    name: 'ABrasil Sistemas',
    auth: { user: null },
    contact: {
        whatsapp: '__AB_WHATSAPP__',
        whatsappDisplay: '__AB_WHATSAPP_DISPLAY__',
        email: '__AB_EMAIL__',
    },
    company: {
        name: 'ABrasil Sistemas',
        legalName: null,
        cnpj: null,
        location: null,
        serviceArea: [],
        businessHours: null,
        phone: null,
        summary: '__AB_COMPANY__',
    },
    analytics: null,
    projectTypes: {},
    selectedType: null,
    blogPosts: [],
    testimonials: [],
    portfolioItems: [],
};

type Target = { path: string; component: string; slug?: string };

export const targets: Target[] = [
    { path: '/', component: 'welcome' },
    { path: '/servicos', component: 'servicos' },
    { path: '/sistemas', component: 'site/sistemas' },
    { path: '/sobre', component: 'sobre' },
    { path: '/contato', component: 'contato' },
    { path: '/politica-de-privacidade', component: 'legal/privacidade' },
    { path: '/termos-de-uso', component: 'legal/termos' },
    { path: '/politica-de-cookies', component: 'legal/cookies' },
    { path: '/blog', component: 'blog-shell' },
    ...services.map((service) => ({
        path: `/${service.slug}`,
        component: service.contactType === 'seo' ? 'site/seo' : 'site/servico',
        slug: service.slug,
    })),
    ...products.map((product) => ({
        path: `/sistemas/${product.slug}`,
        component: 'site/sistema',
        slug: product.slug,
    })),
];

export async function renderTarget({ path, component, slug }: Target) {
    let html = '';

    await createInertiaApp({
        page: {
            component,
            url: path,
            version: null,
            props: { ...sharedProps, slug, errors: {} },
            flash: {},
            rememberedState: {},
        } as never,
        resolve: (name: string) =>
            (pages[`../pages/${name}.tsx`] ?? pages[`./${name}.tsx`]) as never,
        setup: ({ App, props }) => (
            <TooltipProvider delayDuration={0}>
                <App {...props} />
            </TooltipProvider>
        ),
        render: (element) => (html = renderToString(element)),
    });

    return html;
}
