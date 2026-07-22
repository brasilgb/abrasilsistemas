import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Eye, MessageCircle, UserRound } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import BlogPublicLayout from '@/components/blog-public-layout';
import type { User } from '@/types';

type Comment = { id: number; body: string; created_at: string; user: { name: string } };
type Post = {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    body: string;
    cover_image_url?: string;
    published_at: string;
    views: number;
    author: { name: string };
    category?: { name: string; slug: string };
    comments: Comment[];
};
type Props = {
    post: Post;
    relatedPosts: { id: number; title: string; slug: string; excerpt: string }[];
};

const date = (value: string) =>
    new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long' }).format(new Date(value));

function ArticleBody({ content }: { content: string }) {
    const lines = content.replace(/\r\n/g, '\n').split('\n');
    const blocks: ReactNode[] = [];
    let paragraph: string[] = [];
    let list: string[] = [];

    const flushParagraph = () => {
        if (paragraph.length) {
            blocks.push(<p key={`p-${blocks.length}`}>{paragraph.join(' ')}</p>);
            paragraph = [];
        }
    };
    const flushList = () => {
        if (list.length) {
            blocks.push(<ul key={`ul-${blocks.length}`}>{list.map((item, index) => <li key={index}>{item}</li>)}</ul>);
            list = [];
        }
    };

    lines.forEach((rawLine) => {
        const line = rawLine.trim();
        const image = line.match(/^!\[(.*)]\((.+)\)$/);
        if (!line) {
            flushParagraph();
            flushList();
        } else if (image) {
            flushParagraph(); flushList();
            blocks.push(
                <figure key={`image-${blocks.length}`}>
                    <img src={image[2]} alt={image[1]} loading="lazy" />
                    {image[1] && <figcaption>{image[1]}</figcaption>}
                </figure>,
            );
        } else if (line.startsWith('### ')) {
            flushParagraph(); flushList();
            blocks.push(<h3 key={`h3-${blocks.length}`}>{line.slice(4)}</h3>);
        } else if (line.startsWith('## ')) {
            flushParagraph(); flushList();
            blocks.push(<h2 key={`h2-${blocks.length}`}>{line.slice(3)}</h2>);
        } else if (line.startsWith('- ')) {
            flushParagraph();
            list.push(line.slice(2));
        } else {
            flushList();
            paragraph.push(line);
        }
    });
    flushParagraph();
    flushList();

    return <div className="article-content">{blocks}</div>;
}

export default function BlogShow({ post, relatedPosts }: Props) {
    const { auth } = usePage<{ auth: { user: User | null } }>().props;
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);

    const comment = (event: FormEvent) => {
        event.preventDefault();
        setSending(true);
        router.post(`/blog/${post.slug}/comentarios`, { body }, {
            onSuccess: () => setBody(''),
            onFinish: () => setSending(false),
        });
    };

    const articleStructuredData = {
        '@context': 'https://schema.org',
        '@type': 'Article',
        headline: post.title,
        description: post.excerpt,
        datePublished: post.published_at,
        author: { '@type': 'Person', name: post.author.name },
        publisher: { '@type': 'Organization', name: 'ABrasil Sistemas', logo: { '@type': 'ImageObject', url: '/images/logo_ab.png' } },
        image: post.cover_image_url,
    };

    return (
        <BlogPublicLayout>
            <Head title={post.title}>
                <meta name="description" content={post.excerpt} />
                <meta name="robots" content="index, follow, max-image-preview:large" />
                <meta property="og:type" content="article" />
                <meta property="og:title" content={post.title} />
                <meta property="og:description" content={post.excerpt} />
                {post.cover_image_url && <meta property="og:image" content={post.cover_image_url} />}
                <script type="application/ld+json">{JSON.stringify(articleStructuredData)}</script>
            </Head>

            <main>
                <article>
                    <header className="border-b border-slate-200 bg-gradient-to-b from-blue-50 to-white py-14 sm:py-20">
                        <div className="mx-auto max-w-4xl px-5 sm:px-8">
                            <Link href="/blog" className="inline-flex items-center gap-2 text-sm font-bold text-blue-700"><ArrowLeft className="size-4" /> Voltar ao blog</Link>
                            <div className="mt-10 flex flex-wrap items-center gap-3 text-sm text-slate-500">
                                {post.category && <Link href={`/blog?category=${post.category.slug}`} className="rounded-full bg-blue-100 px-3 py-1 font-bold text-blue-700">{post.category.name}</Link>}
                                <span>{date(post.published_at)}</span><span>·</span><span className="inline-flex items-center gap-1"><Eye className="size-4" /> {post.views} leituras</span>
                            </div>
                            <h1 className="mt-6 text-4xl leading-[1.08] font-bold tracking-[-0.045em] text-balance text-slate-950 sm:text-6xl">{post.title}</h1>
                            <p className="mt-6 text-xl leading-8 text-slate-600">{post.excerpt}</p>
                            <div className="mt-7 flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-3 text-sm text-slate-600"><span className="grid size-9 place-items-center rounded-full bg-slate-200"><UserRound className="size-4" /></span><span>Por <strong className="text-slate-900">{post.author.name}</strong></span></div>
                                <a href="#comentarios" className="inline-flex items-center gap-2 rounded-lg border border-blue-200 bg-white px-4 py-2 text-sm font-bold text-blue-700 hover:bg-blue-50"><MessageCircle className="size-4" /> Comentar</a>
                            </div>
                        </div>
                    </header>

                    <div className="mx-auto max-w-4xl px-5 sm:px-8">
                        {post.cover_image_url && <img src={post.cover_image_url} alt="" className="mt-10 max-h-[520px] w-full rounded-2xl object-cover shadow-lg sm:mt-14" />}
                        <ArticleBody content={post.body} />
                    </div>
                </article>

                <section className="mx-auto max-w-4xl px-5 sm:px-8">
                    <div className="mt-16 rounded-2xl bg-blue-700 p-7 text-white sm:flex sm:items-center sm:justify-between sm:gap-8 sm:p-9">
                        <div><h2 className="text-2xl font-bold">Quer aplicar tecnologia na sua empresa?</h2><p className="mt-2 text-sm leading-6 text-blue-100">Converse com a ABrasil Sistemas e descubra o melhor caminho.</p></div>
                        <a href="https://wa.me/5551998931325?text=Ol%C3%A1%2C%20li%20um%20artigo%20no%20blog%20e%20gostaria%20de%20conversar." target="_blank" rel="noreferrer" className="mt-5 inline-flex shrink-0 items-center gap-2 rounded-lg bg-white px-5 py-3 text-sm font-bold text-blue-800 sm:mt-0"><MessageCircle className="size-4" /> Falar conosco</a>
                    </div>
                </section>

                <section id="comentarios" className="mx-auto mt-16 max-w-4xl scroll-mt-36 border-t border-slate-200 px-5 pt-10 sm:px-8">
                    <h2 className="flex items-center gap-2 text-2xl font-bold text-slate-950"><MessageCircle className="size-6 text-blue-700" /> Comentários ({post.comments.length})</h2>
                    {auth.user ? (
                        <form onSubmit={comment} className="mt-6">
                            <div className="mb-4 flex items-center gap-3 rounded-xl bg-blue-50 px-4 py-3">
                                <span className="grid size-9 shrink-0 place-items-center rounded-full bg-blue-100 text-blue-700"><UserRound className="size-4" /></span>
                                <div className="min-w-0 text-sm">
                                    <p className="font-bold text-slate-900">Comentando como {auth.user.name}</p>
                                    <p className="truncate text-xs text-slate-500">{auth.user.email}</p>
                                </div>
                            </div>
                            <textarea value={body} onChange={(event) => setBody(event.target.value)} minLength={3} maxLength={2000} required rows={4} placeholder="Compartilhe sua opinião ou dúvida" className="w-full rounded-xl border border-slate-300 bg-white p-4 text-sm outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100" />
                            <button disabled={sending} className="mt-3 rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50">Enviar para moderação</button>
                        </form>
                    ) : (
                        <p className="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600"><Link href="/login" className="font-bold text-blue-700">Entre na sua conta</Link> para comentar.</p>
                    )}
                    <div className="mt-8 space-y-4">
                        {post.comments.map((commentItem) => <div key={commentItem.id} className="rounded-xl border border-slate-200 bg-white p-5"><div className="flex justify-between gap-4 text-sm"><strong className="text-slate-900">{commentItem.user.name}</strong><span className="text-slate-400">{date(commentItem.created_at)}</span></div><p className="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-700">{commentItem.body}</p></div>)}
                    </div>
                </section>

                {relatedPosts.length > 0 && (
                    <section className="mx-auto mt-16 max-w-4xl px-5 sm:px-8"><h2 className="text-2xl font-bold text-slate-950">Continue lendo</h2><div className="mt-5 grid gap-4 sm:grid-cols-3">{relatedPosts.map((item) => <Link key={item.id} href={`/blog/${item.slug}`} className="group rounded-xl border border-slate-200 p-5 transition hover:border-blue-200 hover:shadow-md"><strong className="leading-6 text-slate-900 group-hover:text-blue-700">{item.title}</strong><p className="mt-2 line-clamp-3 text-sm leading-6 text-slate-600">{item.excerpt}</p><span className="mt-4 inline-flex items-center gap-1 text-xs font-bold text-blue-700">Ler artigo <ArrowRight className="size-3" /></span></Link>)}</div></section>
                )}
            </main>
        </BlogPublicLayout>
    );
}
