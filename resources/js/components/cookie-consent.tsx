import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState, useSyncExternalStore } from 'react';
import type { AnalyticsConfig } from '@/lib/analytics';
import {
    CONSENT_EVENT,
    CONSENT_STORAGE_KEY as STORAGE_KEY,
    hasAnalyticsConsent,
    loadGa4,
    trackEvent,
} from '@/lib/analytics';

type Consent = 'all' | 'necessary';

function subscribeConsent(onStoreChange: () => void) {
    window.addEventListener(CONSENT_EVENT, onStoreChange);
    window.addEventListener('storage', onStoreChange);

    return () => {
        window.removeEventListener(CONSENT_EVENT, onStoreChange);
        window.removeEventListener('storage', onStoreChange);
    };
}

function getConsentSnapshot(): string | null {
    try {
        return window.localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

function getServerConsentSnapshot(): null {
    return null;
}

export function CookieConsent() {
    const consent = useSyncExternalStore(
        subscribeConsent,
        getConsentSnapshot,
        getServerConsentSnapshot,
    );
    const [dismissed, setDismissed] = useState(false);
    const visible = consent === null && !dismissed;
    const analytics = usePage<{ analytics?: AnalyticsConfig | null }>().props
        .analytics;

    // GA4 só com consentimento: na carga (escolha já salva) e logo após o "Aceitar todos".
    useEffect(() => {
        if (!analytics) {
            return;
        }

        const load = () => hasAnalyticsConsent() && loadGa4(analytics);
        // Início de atendimento: qualquer link de WhatsApp do site.
        const onClick = (event: MouseEvent) => {
            const link = (event.target as Element | null)?.closest?.('a[href]');
            const href = link?.getAttribute('href') ?? '';

            if (/^https:\/\/(wa\.me|api\.whatsapp\.com)\//.test(href)) {
                trackEvent('contact_whatsapp', {
                    page_path: window.location.pathname,
                });
            }
        };

        load();
        window.addEventListener(CONSENT_EVENT, load);
        document.addEventListener('click', onClick);

        return () => {
            window.removeEventListener(CONSENT_EVENT, load);
            document.removeEventListener('click', onClick);
        };
    }, [analytics]);

    function choose(consent: Consent) {
        try {
            window.localStorage.setItem(STORAGE_KEY, consent);
        } catch {
            // Nothing to persist if storage is blocked — just stop showing the banner this session.
        }

        setDismissed(true);
        window.dispatchEvent(new Event(CONSENT_EVENT));
    }

    if (!visible) {
        return null;
    }

    return (
        <div className="fixed inset-x-0 bottom-0 z-[60] border-t border-slate-200 bg-white/97 px-5 py-5 shadow-[0_-10px_30px_rgba(0,0,0,.1)] backdrop-blur sm:px-8">
            <div className="mx-auto flex max-w-[86rem] flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="max-w-2xl text-sm leading-6 text-slate-600">
                    Utilizamos cookies para melhorar sua experiência e entender
                    como nosso site é utilizado. Você pode aceitar todos os
                    cookies ou configurar suas preferências.
                </p>
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    <Link
                        href="/politica-de-cookies"
                        className="rounded-full px-3 py-2.5 text-sm font-bold text-slate-500 underline underline-offset-2 transition hover:text-slate-800"
                    >
                        Configurar
                    </Link>
                    <button
                        type="button"
                        onClick={() => choose('necessary')}
                        className="rounded-full border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                    >
                        Somente necessários
                    </button>
                    <button
                        type="button"
                        onClick={() => choose('all')}
                        className="rounded-full bg-blue-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-800"
                    >
                        Aceitar todos
                    </button>
                </div>
            </div>
        </div>
    );
}
