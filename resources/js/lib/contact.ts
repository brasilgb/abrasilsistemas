import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

export type ContactInfo = {
    whatsapp: string;
    whatsappDisplay: string;
    email: string;
};

// Mirrors config/contact.php's defaults. Used only as a safety net for pages
// rendered outside the normal Inertia middleware pipeline (e.g. the 404 page,
// rendered directly from the exception handler in bootstrap/app.php), where
// HandleInertiaRequests::share() never runs and `contact` isn't shared.
const FALLBACK_CONTACT: ContactInfo = {
    whatsapp: '5551998931325',
    whatsappDisplay: '(51) 99893-1325',
    email: 'contato@abrasilsistemas.com.br',
};

/**
 * Reads the ABrasil contact info (WhatsApp number, e-mail) shared globally
 * via Inertia (see HandleInertiaRequests::share()) instead of hardcoding it
 * across every public page/component.
 */
export function useContact(): ContactInfo {
    return (
        usePage<{ contact?: ContactInfo }>().props.contact ?? FALLBACK_CONTACT
    );
}

/**
 * Builds a wa.me link from the shared WhatsApp number and a page-specific
 * pre-filled message.
 */
export function buildWhatsappUrl(
    whatsappNumber: string,
    message: string,
): string {
    return `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`;
}

/**
 * Link do WhatsApp do site com a página de origem na última linha ("Página: /caminho").
 * O atendimento (App\Services\WhatsappInbox) lê essa linha na primeira mensagem do contato
 * e mostra de onde ele veio.
 */
export function useSiteWhatsappUrl(message: string): string {
    const contact = useContact();
    const path = usePage().url.split(/[?#]/)[0] || '/';

    return buildWhatsappUrl(contact.whatsapp, `${message}\n\nPágina: ${path}`);
}

const SOURCE_PAGE_KEY = 'ab:source-page';

/**
 * Guarda a última página institucional visitada (menos o próprio contato), para a
 * solicitação registrar de onde a pessoa veio. Com a navegação do Inertia o
 * document.referrer não muda, por isso fica no sessionStorage, que pode falhar
 * (aba privada, bloqueio): nesse caso a origem só fica em branco.
 */
function rememberSourcePage(pathname: string) {
    if (pathname.startsWith('/contato')) {
        return;
    }

    try {
        window.sessionStorage.setItem(SOURCE_PAGE_KEY, pathname);
    } catch {
        // Sem armazenamento: a solicitação fica sem página de origem.
    }
}

/** Registra cada página pública visitada (inclusive navegações do Inertia entre páginas iguais). */
export function useRememberSourcePage() {
    const { url } = usePage();

    useEffect(() => {
        rememberSourcePage(window.location.pathname);
    }, [url]);
}

/** Página de origem para o formulário de contato (null quando desconhecida). */
export function readSourcePage(): string | null {
    try {
        const stored = window.sessionStorage.getItem(SOURCE_PAGE_KEY);

        if (stored) {
            return stored;
        }
    } catch {
        // Segue para o referrer.
    }

    try {
        const referrer = new URL(document.referrer);

        if (
            referrer.origin === window.location.origin &&
            !referrer.pathname.startsWith('/contato')
        ) {
            return referrer.pathname;
        }
    } catch {
        // Sem referrer válido.
    }

    return null;
}
