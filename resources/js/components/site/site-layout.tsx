import type { ReactNode } from 'react';
import { CookieConsent } from '@/components/cookie-consent';
import { PublicFooter } from '@/components/public-footer';
import { SeoHead } from '@/components/site/seo-head';
import { SiteHeader } from '@/components/site/site-header';
import { useRememberSourcePage } from '@/lib/contact';

/**
 * Estrutura comum das páginas institucionais: SEO, cabeçalho, rodapé, botão de
 * WhatsApp e aviso de cookies. `ab-static-light` mantém o site claro mesmo com
 * o sistema operacional do visitante em modo escuro (ver app.css).
 */
export function SiteLayout({ children }: { children: ReactNode }) {
    useRememberSourcePage();

    return (
        <>
            <SeoHead />
            <div className="ab-public-site ab-static-light min-h-screen overflow-x-hidden bg-white text-slate-900 antialiased selection:bg-blue-100 selection:text-slate-900">
                <a
                    href="#conteudo"
                    className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[60] focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-900"
                >
                    Pular para o conteúdo
                </a>
                <SiteHeader />
                <main id="conteudo">{children}</main>
                <PublicFooter />
                <CookieConsent />
            </div>
        </>
    );
}
