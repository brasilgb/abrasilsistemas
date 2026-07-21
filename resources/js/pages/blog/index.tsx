import BlogPublicLayout from '@/components/blog-public-layout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Eye, Search } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Post = {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    cover_image_url?: string;
    views: number;
    published_at: string;
    category?: { name: string; slug: string };
    author: { name: string };
};
type Props = {
    posts: {
        data: Post[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    categories: {
        id: number;
        name: string;
        slug: string;
        posts_count: number;
    }[];
    popularPosts: Post[];
    filters: { category?: string; search?: string };
};
const date = (value: string) =>
    new Intl.DateTimeFormat('pt-BR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    }).format(new Date(value));

export default function BlogIndex({
    posts,
    categories,
    popularPosts,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            '/blog',
            { search, category: filters.category },
            { preserveState: true },
        );
    };
    return (
        <BlogPublicLayout>
            <Head title="Blog">
                <meta
                    name="description"
                    content="Artigos sobre tecnologia, gestão e os projetos da AB Sistemas."
                />
            </Head>
            <section className="border-b border-white/8 bg-gradient-to-b from-sky-500/10 to-transparent py-20">
                <div className="mx-auto max-w-7xl px-5 sm:px-8">
                    <p className="text-xs font-bold tracking-[.2em] text-sky-300 uppercase">
                        Conteúdo AB Sistemas
                    </p>
                    <h1 className="mt-4 text-5xl font-semibold tracking-tight">
                        Ideias para transformar negócios.
                    </h1>
                    <p className="mt-5 max-w-2xl text-lg text-slate-400">
                        Tecnologia, gestão e experiências dos projetos que
                        construímos.
                    </p>
                </div>
            </section>
            <main className="mx-auto grid max-w-7xl gap-12 px-5 py-14 sm:px-8 lg:grid-cols-[1fr_320px]">
                <div>
                    <form onSubmit={submit} className="mb-8 flex gap-2">
                        <div className="relative flex-1">
                            <Search className="absolute top-3 left-3 size-5 text-slate-500" />
                            <input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar artigos"
                                className="w-full rounded-xl border border-white/10 bg-white/5 py-2.5 pr-4 pl-11 outline-none focus:border-sky-400"
                            />
                        </div>
                        <button className="rounded-xl bg-sky-400 px-5 font-bold text-slate-950">
                            Buscar
                        </button>
                    </form>
                    {posts.data.length ? (
                        <div className="grid gap-6 md:grid-cols-2">
                            {posts.data.map((post) => (
                                <article
                                    key={post.id}
                                    className="overflow-hidden rounded-2xl border border-white/10 bg-white/[.035] transition hover:border-sky-300/30"
                                >
                                    {post.cover_image_url && (
                                        <img
                                            src={post.cover_image_url}
                                            alt=""
                                            className="h-48 w-full object-cover"
                                        />
                                    )}
                                    <div className="p-6">
                                        <div className="flex gap-3 text-xs text-slate-500">
                                            {post.category && (
                                                <span className="text-sky-300">
                                                    {post.category.name}
                                                </span>
                                            )}
                                            <span>
                                                {date(post.published_at)}
                                            </span>
                                        </div>
                                        <h2 className="mt-3 text-xl font-bold">
                                            <Link href={`/blog/${post.slug}`}>
                                                {post.title}
                                            </Link>
                                        </h2>
                                        <p className="mt-3 text-sm leading-6 text-slate-400">
                                            {post.excerpt}
                                        </p>
                                        <Link
                                            href={`/blog/${post.slug}`}
                                            className="mt-5 inline-flex items-center gap-2 text-sm font-bold text-sky-300"
                                        >
                                            Ler artigo{' '}
                                            <ArrowRight className="size-4" />
                                        </Link>
                                    </div>
                                </article>
                            ))}
                        </div>
                    ) : (
                        <div className="rounded-2xl border border-white/10 p-10 text-center text-slate-400">
                            Nenhum artigo encontrado.
                        </div>
                    )}
                    <div className="mt-10 flex flex-wrap gap-2">
                        {posts.links.map(
                            (link, i) =>
                                link.url && (
                                    <Link
                                        key={i}
                                        href={link.url}
                                        preserveScroll
                                        className={`rounded-lg border px-3 py-2 text-sm ${link.active ? 'border-sky-400 bg-sky-400 text-slate-950' : 'border-white/10 text-slate-400'}`}
                                        dangerouslySetInnerHTML={{
                                            __html: link.label,
                                        }}
                                    />
                                ),
                        )}
                    </div>
                </div>
                <aside className="space-y-8">
                    <section>
                        <h2 className="text-lg font-bold">Categorias</h2>
                        <div className="mt-4 space-y-2">
                            <Link
                                href="/blog"
                                className="flex justify-between rounded-lg px-3 py-2 text-slate-300 hover:bg-white/5"
                            >
                                <span>Todas</span>
                            </Link>
                            {categories.map((category) => (
                                <Link
                                    key={category.id}
                                    href={`/blog?category=${category.slug}`}
                                    className="flex justify-between rounded-lg px-3 py-2 text-slate-300 hover:bg-white/5"
                                >
                                    <span>{category.name}</span>
                                    <span className="text-slate-600">
                                        {category.posts_count}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </section>
                    <section>
                        <h2 className="text-lg font-bold">Mais populares</h2>
                        <div className="mt-4 space-y-4">
                            {popularPosts.map((post) => (
                                <Link
                                    key={post.id}
                                    href={`/blog/${post.slug}`}
                                    className="block border-b border-white/8 pb-4"
                                >
                                    <span className="font-medium">
                                        {post.title}
                                    </span>
                                    <span className="mt-2 flex items-center gap-1 text-xs text-slate-500">
                                        <Eye className="size-3" />
                                        {post.views} visualizações
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </section>
                </aside>
            </main>
        </BlogPublicLayout>
    );
}
