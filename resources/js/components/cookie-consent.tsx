import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Consent = 'all' | 'necessary';

const STORAGE_KEY = 'ab-cookie-consent';

export function CookieConsent() {
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        try {
            if (!window.localStorage.getItem(STORAGE_KEY)) {
                setVisible(true);
            }
        } catch {
            // localStorage unavailable (private mode, blocked storage, etc.) — show the banner anyway.
            setVisible(true);
        }
    }, []);

    function choose(consent: Consent) {
        try {
            window.localStorage.setItem(STORAGE_KEY, consent);
        } catch {
            // Nothing to persist if storage is blocked — just stop showing the banner this session.
        }
        setVisible(false);
    }

    if (!visible) {
        return null;
    }

    return (
        <div className="fixed inset-x-0 bottom-0 z-[60] border-t border-slate-200 bg-white/97 px-5 py-5 shadow-[0_-10px_30px_rgba(0,0,0,.1)] backdrop-blur sm:px-8">
            <div className="mx-auto flex max-w-[86rem] flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="max-w-2xl text-sm leading-6 text-slate-600">
                    Utilizamos cookies para melhorar sua experiência e entender como nosso site é utilizado. Você pode aceitar todos os
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
