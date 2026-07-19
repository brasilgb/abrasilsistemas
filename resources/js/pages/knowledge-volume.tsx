import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    CheckCircle2,
    Clock3,
    Code2,
    Mail,
} from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login, register } from '@/routes';
import type { User } from '@/types';

type VolumeReference = {
    number: number;
    slug: string;
    title: string;
};

type Props = {
    collection: {
        slug: string;
        name: string;
    };
    volume: VolumeReference & {
        track: string;
        summary: string;
        technologies: string[];
        status: string;
    };
    previousVolume: VolumeReference | null;
    nextVolume: VolumeReference | null;
    ebook: {
        id: number;
        title: string;
        price_cents: number;
        currency: string;
    } | null;
};

export default function KnowledgeVolume({
    collection,
    volume,
    previousVolume,
    nextVolume,
    ebook,
}: Props) {
    const { auth } = usePage<{ auth: { user: User | null } }>().props;
    const user = auth.user;

    return (
        <>
            <Head title={`${volume.title} — ${collection.name}`}>
                <meta name="description" content={volume.summary} />
                <meta name="robots" content="index, follow" />
            </Head>

            <div className="min-h-screen bg-[#07111f] text-white selection:bg-amber-300 selection:text-slate-950">
                <header className="border-b border-white/8 bg-[#07111f]/90 backdrop-blur-xl">
                    <div className="mx-auto flex h-20 max-w-6xl items-center justify-between px-5 sm:px-8">
                        <Link
                            href="/conhecimento"
                            className="flex items-center gap-3"
                        >
                            <img
                                src="/images/logo_ab.png"
                                alt="AB Sistemas"
                                className="size-11 rounded-xl border border-white/60 object-contain"
                            />
                            <div className="leading-none">
                                <span className="block text-sm font-bold">
                                    AB Sistemas
                                </span>
                                <span className="mt-1 block text-[8px] font-semibold tracking-[0.24em] text-amber-300 uppercase">
                                    Conhecimento
                                </span>
                            </div>
                        </Link>
                        <div className="flex items-center gap-2">
                            <Link
                                href="/conhecimento#trilhas"
                                className="hidden items-center gap-2 rounded-full border border-white/12 bg-white/5 px-4 py-2 text-xs font-semibold hover:bg-white/10 sm:inline-flex"
                            >
                                <ArrowLeft className="size-3.5" />
                                Todos os volumes
                            </Link>
                            <Link
                                href={
                                    user ? '/minha-biblioteca' : login()
                                }
                                className="inline-flex rounded-full border border-white/12 px-4 py-2 text-xs font-semibold hover:bg-white/10"
                            >
                                {user ? 'Minha conta' : 'Entrar'}
                            </Link>
                            {!user && (
                                <Link
                                    href={register()}
                                    className="inline-flex rounded-full bg-amber-300 px-4 py-2 text-xs font-bold text-slate-950 hover:bg-amber-200"
                                >
                                    Criar conta
                                </Link>
                            )}
                        </div>
                    </div>
                </header>

                <main>
                    <section className="relative overflow-hidden border-b border-white/8">
                        <div className="pointer-events-none absolute -top-52 right-[-15rem] size-[40rem] rounded-full bg-amber-400/10 blur-[140px]" />
                        <div className="relative mx-auto max-w-6xl px-5 py-20 sm:px-8 sm:py-28">
                            <div className="flex flex-wrap items-center gap-3 text-xs font-bold tracking-[0.16em] uppercase">
                                <span className="rounded-full border border-amber-300/20 bg-amber-300/7 px-3 py-1.5 text-amber-200">
                                    Volume {volume.number} de 12
                                </span>
                                <span className="text-slate-500">
                                    {volume.track}
                                </span>
                            </div>
                            <h1 className="mt-7 max-w-4xl text-5xl leading-none font-semibold tracking-[-0.05em] sm:text-7xl">
                                {volume.title}
                            </h1>
                            <p className="mt-7 max-w-3xl text-lg leading-8 text-slate-400">
                                {volume.summary}
                            </p>
                            <div className="mt-9 flex flex-wrap gap-2">
                                {volume.technologies.map((technology) => (
                                    <span
                                        key={technology}
                                        className="rounded-lg border border-white/9 bg-white/5 px-3 py-2 text-xs text-slate-300"
                                    >
                                        {technology}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="mx-auto grid max-w-6xl gap-6 px-5 py-16 sm:px-8 lg:grid-cols-[1fr_0.65fr]">
                        <article className="rounded-2xl border border-white/9 bg-white/[0.035] p-7 sm:p-9">
                            <BookOpen className="size-8 text-amber-300" />
                            <h2 className="mt-6 text-2xl font-bold">
                                Conteúdo do volume
                            </h2>
                            <p className="mt-4 leading-7 text-slate-400">
                                O sumário, as amostras, o código-fonte e os
                                materiais complementares serão disponibilizados
                                nesta página conforme a produção avançar.
                            </p>
                            <div className="mt-7 flex items-center gap-3 text-sm text-emerald-300">
                                <CheckCircle2 className="size-5" />
                                Conteúdo livre e e-book sem progressão obrigatória.
                            </div>
                        </article>

                        <aside className="rounded-2xl border border-white/9 bg-[#0a1728] p-7 sm:p-9">
                            <div className="flex items-center gap-3 text-sm text-slate-400">
                                <Clock3 className="size-5 text-sky-300" />
                                Situação editorial
                            </div>
                            <strong className="mt-4 block text-2xl">
                                {volume.status}
                            </strong>
                            <p className="mt-4 text-sm leading-6 text-slate-500">
                                As datas serão divulgadas quando o volume atingir
                                o nível de qualidade definido para publicação.
                            </p>
                            <a
                                href={`mailto:contato@absistemas.com.br?subject=Quero acompanhar o Volume ${volume.number} - ${volume.title}`}
                                className="mt-7 inline-flex items-center gap-2 text-sm font-bold text-amber-300 hover:text-amber-200"
                            >
                                Receber novidades
                                <Mail className="size-4" />
                            </a>
                            {ebook && (
                                <div className="mt-8 border-t border-white/8 pt-7">
                                    <span className="text-xs font-bold tracking-wide text-amber-300 uppercase">
                                        E-book disponível
                                    </span>
                                    <strong className="mt-2 block text-3xl">
                                        {(ebook.price_cents / 100).toLocaleString(
                                            'pt-BR',
                                            {
                                                style: 'currency',
                                                currency: ebook.currency,
                                            },
                                        )}
                                    </strong>
                                    {user ? (
                                        <Form
                                            action={`/conhecimento/ebooks/${ebook.id}/pix`}
                                            method="post"
                                            className="mt-5 grid gap-3"
                                        >
                                            {({ processing, errors }) => (
                                                <>
                                                    <div className="grid gap-2">
                                                        <Label htmlFor="document">
                                                            CPF ou CNPJ
                                                        </Label>
                                                        <Input
                                                            id="document"
                                                            name="document"
                                                            inputMode="numeric"
                                                            pattern="[0-9]*"
                                                            placeholder="Somente números"
                                                            required
                                                            className="border-white/10 bg-white/5"
                                                        />
                                                        <InputError
                                                            message={
                                                                errors.document
                                                            }
                                                        />
                                                    </div>
                                                    <Button
                                                        disabled={processing}
                                                        className="bg-amber-300 text-slate-950 hover:bg-amber-200"
                                                    >
                                                        Gerar PIX
                                                    </Button>
                                                </>
                                            )}
                                        </Form>
                                    ) : (
                                        <Link
                                            href={register()}
                                            className="mt-5 inline-flex h-10 w-full items-center justify-center rounded-lg bg-amber-300 text-sm font-bold text-slate-950 hover:bg-amber-200"
                                        >
                                            Criar conta para comprar
                                        </Link>
                                    )}
                                </div>
                            )}
                        </aside>
                    </section>

                    <nav className="mx-auto grid max-w-6xl gap-4 px-5 pb-20 sm:px-8 md:grid-cols-2">
                        {previousVolume ? (
                            <Link
                                href={`/conhecimento/volumes/${previousVolume.slug}`}
                                className="rounded-2xl border border-white/9 bg-white/[0.03] p-5 hover:bg-white/[0.06]"
                            >
                                <span className="flex items-center gap-2 text-xs text-slate-500">
                                    <ArrowLeft className="size-3.5" /> Volume
                                    anterior
                                </span>
                                <strong className="mt-2 block">
                                    {previousVolume.number}. {previousVolume.title}
                                </strong>
                            </Link>
                        ) : (
                            <div />
                        )}
                        {nextVolume && (
                            <Link
                                href={`/conhecimento/volumes/${nextVolume.slug}`}
                                className="rounded-2xl border border-white/9 bg-white/[0.03] p-5 text-right hover:bg-white/[0.06]"
                            >
                                <span className="flex items-center justify-end gap-2 text-xs text-slate-500">
                                    Próximo volume
                                    <ArrowRight className="size-3.5" />
                                </span>
                                <strong className="mt-2 block">
                                    {nextVolume.number}. {nextVolume.title}
                                </strong>
                            </Link>
                        )}
                    </nav>
                </main>

                <footer className="border-t border-white/8 bg-[#040a13] py-8 text-center text-xs text-slate-600">
                    <span className="inline-flex items-center gap-2">
                        <Code2 className="size-3.5" /> AB Sistemas Conhecimento ·{' '}
                        {new Date().getFullYear()}
                    </span>
                </footer>
            </div>
        </>
    );
}
