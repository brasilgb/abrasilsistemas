import { Head, Link, router } from '@inertiajs/react';
import { Check, Copy, LoaderCircle, QrCode } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    ebook: {
        id: number;
        title: string;
        volume_slug: string;
        price_cents: number;
        currency: string;
    };
    order: {
        id: number;
        status: string;
        expires_at: string | null;
    };
    pix: {
        qr_code: string | null;
        copy_paste: string | null;
    };
};

export default function EbookCheckout({ ebook, order, pix }: Props) {
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        const interval = window.setInterval(async () => {
            const response = await fetch(
                `/conhecimento/pedidos/${order.id}/status`,
                { headers: { Accept: 'application/json' } },
            );

            if (!response.ok) {
                return;
            }

            const data = (await response.json()) as { paid: boolean };

            if (data.paid) {
                window.clearInterval(interval);
                router.visit('/minha-biblioteca');
            }
        }, 5000);

        return () => window.clearInterval(interval);
    }, [order.id]);

    const copyPix = async () => {
        if (!pix.copy_paste) {
            return;
        }

        await navigator.clipboard.writeText(pix.copy_paste);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 2500);
    };

    return (
        <>
            <Head title={`Pagamento PIX — ${ebook.title}`} />

            <div className="min-h-screen bg-[#07111f] px-5 py-12 text-white">
                <main className="mx-auto max-w-lg rounded-3xl border border-white/10 bg-white/[0.04] p-6 text-center shadow-2xl sm:p-9">
                    <div className="mx-auto grid size-12 place-items-center rounded-full bg-emerald-400/10 text-emerald-300">
                        <QrCode className="size-6" />
                    </div>
                    <h1 className="mt-5 text-2xl font-bold">Pague com PIX</h1>
                    <p className="mt-2 text-sm text-slate-400">
                        {ebook.title}
                    </p>
                    <strong className="mt-3 block text-3xl">
                        {(ebook.price_cents / 100).toLocaleString('pt-BR', {
                            style: 'currency',
                            currency: ebook.currency,
                        })}
                    </strong>

                    {pix.qr_code && (
                        <img
                            src={`data:image/png;base64,${pix.qr_code}`}
                            alt="QR Code PIX"
                            className="mx-auto mt-7 size-64 rounded-xl bg-white p-3"
                        />
                    )}

                    {pix.copy_paste && (
                        <>
                            <textarea
                                readOnly
                                value={pix.copy_paste}
                                className="mt-5 h-24 w-full resize-none rounded-xl border border-white/10 bg-black/20 p-3 text-xs text-slate-300"
                            />
                            <Button
                                type="button"
                                onClick={copyPix}
                                className="mt-3 w-full bg-amber-300 text-slate-950 hover:bg-amber-200"
                            >
                                {copied ? (
                                    <Check className="size-4" />
                                ) : (
                                    <Copy className="size-4" />
                                )}
                                {copied ? 'Código copiado' : 'Copiar código PIX'}
                            </Button>
                        </>
                    )}

                    <div className="mt-6 flex items-center justify-center gap-2 text-sm text-slate-400">
                        <LoaderCircle className="size-4 animate-spin text-emerald-300" />
                        Aguardando confirmação do pagamento
                    </div>
                    <p className="mt-3 text-xs leading-5 text-slate-500">
                        Após a aprovação, o e-book será liberado automaticamente
                        em Minha biblioteca.
                    </p>
                    <Link
                        href={`/conhecimento/volumes/${ebook.volume_slug}`}
                        className="mt-6 inline-block text-xs text-slate-500 underline hover:text-white"
                    >
                        Voltar ao volume
                    </Link>
                </main>
            </div>
        </>
    );
}
