import { Head, Link } from '@inertiajs/react';
import { ArrowRight, MessageCircle } from 'lucide-react';
import { PublicBrand } from '@/components/public-brand';
import { buildWhatsappUrl, useContact } from '@/lib/contact';

export default function NotFound() {
    const contact = useContact();
    const whatsappUrl = buildWhatsappUrl(contact.whatsapp, 'Olá, cheguei numa página que não existe mais no site da ABrasil.');

    return (
        <>
            <Head title="Página não encontrada | ABrasil Sistemas">
                <meta name="robots" content="noindex, nofollow" />
                <meta name="theme-color" content="#08111f" />
            </Head>

            <div className="flex min-h-screen flex-col items-center justify-center bg-slate-950 px-5 text-center text-white">
                <div className="mb-10">
                    <PublicBrand inverse />
                </div>
                <p className="text-sm font-extrabold tracking-[0.18em] text-sky-300 uppercase">Erro 404</p>
                <h1 className="mt-4 text-4xl font-black tracking-[-0.04em] text-balance sm:text-5xl">Página não encontrada</h1>
                <p className="mt-4 max-w-md text-slate-400">
                    O endereço que você tentou acessar não existe ou foi movido.
                </p>
                <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                    <Link
                        href="/"
                        className="inline-flex h-13 items-center justify-center gap-2 rounded-full bg-sky-400 px-7 text-sm font-extrabold text-slate-950 transition hover:-translate-y-0.5 hover:bg-sky-300"
                    >
                        Voltar para a Home <ArrowRight className="size-4" />
                    </Link>
                    <a
                        href={whatsappUrl}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex h-13 items-center justify-center gap-2 rounded-full border border-white/15 px-7 text-sm font-bold text-white transition hover:bg-white/5"
                    >
                        Falar conosco <MessageCircle className="size-4" />
                    </a>
                </div>
            </div>
        </>
    );
}
