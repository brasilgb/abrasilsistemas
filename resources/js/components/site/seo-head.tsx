import { Head, usePage } from '@inertiajs/react';

export type SeoTags = {
    title: string;
    description?: string;
    canonical?: string;
    robots?: string;
    ogType?: string;
    ogTitle?: string;
    ogDescription?: string;
    ogImage?: string;
    ogImageAlt?: string;
    twitterTitle?: string;
    twitterDescription?: string;
    siteName?: string;
    locale?: string;
    jsonLd?: unknown;
};

/**
 * Renderiza as tags de SEO enviadas pelo servidor (App\Support\Seo::tags).
 * Cada head-key é igual ao data-inertia da mesma tag em app.blade.php, então o
 * Inertia substitui a versão do HTML inicial em vez de duplicá-la, e as tags
 * continuam corretas em navegações feitas pelo cliente.
 */
export function SeoHead() {
    const seo = usePage<{ seo?: SeoTags }>().props.seo;

    if (!seo) {
        return null;
    }

    return (
        <Head title={seo.title}>
            {seo.description && (
                <meta
                    head-key="description"
                    name="description"
                    content={seo.description}
                />
            )}
            {seo.robots && (
                <meta head-key="robots" name="robots" content={seo.robots} />
            )}
            {seo.canonical && (
                <link
                    head-key="canonical"
                    rel="canonical"
                    href={seo.canonical}
                />
            )}
            {seo.siteName && (
                <meta
                    head-key="og:site_name"
                    property="og:site_name"
                    content={seo.siteName}
                />
            )}
            {seo.locale && (
                <meta
                    head-key="og:locale"
                    property="og:locale"
                    content={seo.locale}
                />
            )}
            <meta
                head-key="og:type"
                property="og:type"
                content={seo.ogType ?? 'website'}
            />
            <meta
                head-key="og:title"
                property="og:title"
                content={seo.ogTitle ?? seo.title}
            />
            {(seo.ogDescription ?? seo.description) && (
                <meta
                    head-key="og:description"
                    property="og:description"
                    content={seo.ogDescription ?? seo.description}
                />
            )}
            {seo.canonical && (
                <meta
                    head-key="og:url"
                    property="og:url"
                    content={seo.canonical}
                />
            )}
            {seo.ogImage && (
                <meta
                    head-key="og:image"
                    property="og:image"
                    content={seo.ogImage}
                />
            )}
            {seo.ogImage && seo.ogImageAlt && (
                <meta
                    head-key="og:image:alt"
                    property="og:image:alt"
                    content={seo.ogImageAlt}
                />
            )}
            {seo.ogImage && (
                <meta
                    head-key="twitter:card"
                    name="twitter:card"
                    content="summary_large_image"
                />
            )}
            {seo.ogImage && (
                <meta
                    head-key="twitter:image"
                    name="twitter:image"
                    content={seo.ogImage}
                />
            )}
            <meta
                head-key="twitter:title"
                name="twitter:title"
                content={seo.twitterTitle ?? seo.ogTitle ?? seo.title}
            />
            {(seo.twitterDescription ?? seo.ogDescription ?? seo.description) && (
                <meta
                    head-key="twitter:description"
                    name="twitter:description"
                    content={
                        seo.twitterDescription ??
                        seo.ogDescription ??
                        seo.description
                    }
                />
            )}
            {seo.jsonLd ? (
                <script head-key="json-ld" type="application/ld+json">
                    {JSON.stringify(seo.jsonLd)}
                </script>
            ) : null}
        </Head>
    );
}
