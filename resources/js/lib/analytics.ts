/**
 * Google Analytics 4 do site público (SEO → Integrações). A tag só é carregada depois
 * do "Aceitar todos" no banner de cookies (LGPD) e no máximo uma vez por página: a
 * navegação do Inertia não recarrega o documento, e as visualizações de página das
 * trocas de rota vêm da medição otimizada do GA4 (eventos de histórico do navegador).
 */
export type AnalyticsConfig = {
    measurementId: string;
    events: string[];
};

export const CONSENT_STORAGE_KEY = 'ab-cookie-consent';
export const CONSENT_EVENT = 'ab:cookie-consent';

const SCRIPT_ID = 'ab-ga4';

type Gtag = (...args: unknown[]) => void;

declare global {
    interface Window {
        dataLayer?: unknown[];
        gtag?: Gtag;
    }
}

let enabledEvents: string[] = [];

export function hasAnalyticsConsent(): boolean {
    try {
        return window.localStorage.getItem(CONSENT_STORAGE_KEY) === 'all';
    } catch {
        return false;
    }
}

export function loadGa4(config: AnalyticsConfig): void {
    enabledEvents = config.events;

    if (
        !/^G-[A-Z0-9]{4,16}$/.test(config.measurementId) ||
        document.getElementById(SCRIPT_ID)
    ) {
        return;
    }

    window.dataLayer = window.dataLayer ?? [];
    window.gtag = function gtag() {
        // eslint-disable-next-line prefer-rest-params
        window.dataLayer!.push(arguments);
    };
    window.gtag('js', new Date());
    window.gtag('config', config.measurementId);

    const script = document.createElement('script');
    script.id = SCRIPT_ID;
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(config.measurementId)}`;
    document.head.appendChild(script);
}

/** Envia um evento comercial, se a tag estiver carregada e o evento ativo no painel. */
export function trackEvent(
    name: string,
    params: Record<string, string> = {},
): void {
    if (window.gtag && enabledEvents.includes(name)) {
        window.gtag('event', name, params);
    }
}
