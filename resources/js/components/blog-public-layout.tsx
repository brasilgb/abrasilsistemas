import type { PropsWithChildren } from 'react';
import { CookieConsent } from '@/components/cookie-consent';
import { PublicFooter } from '@/components/public-footer';
import { SiteHeader } from '@/components/site/site-header';
import { WhatsAppFloat } from '@/components/whatsapp-float';
import { useRememberSourcePage } from '@/lib/contact';

/**
 * Estrutura do blog público: o mesmo cabeçalho, rodapé e botão de WhatsApp do site
 * institucional. O SEO continua nas próprias páginas do blog (Head), por isso não usa
 * o SiteLayout. O cabeçalho é fixo: `pt-16` reserva a altura dele.
 */
export default function BlogPublicLayout({ children }: PropsWithChildren) {
    useRememberSourcePage();

    return (
        <div className="ab-public-site ab-static-light min-h-screen overflow-x-hidden bg-white text-slate-900 antialiased selection:bg-blue-100 selection:text-slate-900">
            <a
                href="#conteudo"
                className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[60] focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-slate-900"
            >
                Pular para o conteúdo
            </a>
            <SiteHeader />
            {/* As páginas do blog já têm o próprio <main>. */}
            <div id="conteudo" className="pt-16">
                {children}
            </div>
            <PublicFooter />
            <WhatsAppFloat />
            <CookieConsent />
        </div>
    );
}
