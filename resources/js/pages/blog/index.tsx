import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowRight, BookOpen, Eye, Search, Sparkles, UserPlus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import BlogPublicLayout from '@/components/blog-public-layout';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import type { User } from '@/types';

type Post = {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    cover_image_url?: string;
    views: number;
    featured: boolean;
    published_at: string;
    category?: { name: string; slug: string };
    author: { name: string };
};

type Props = {
    posts: {
        data: Post[];
        current_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    categories: { id: number; name: string; slug: string; posts_count: number }[];
    popularPosts: Post[];
    filters: { category?: string; search?: string };
};

const date = (value: string) =>
    new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(value));

export default function BlogIndex({ posts, categories, popularPosts, filters }: Props) {
    const { auth } = usePage<{ auth: { user: User | null } }>().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const featured = posts.current_page === 1 && !filters.search && !filters.category
        ? (posts.data.find((post) => post.featured) ?? posts.data[0])
        : null;
    const articles = featured ? posts.data.filter((post) => post.id !== featured.id) : posts.data;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get('/blog', { search, category: filters.category }, { preserveState: true });
    };

    return (
        <BlogPublicLayout>
            <Head title="Blog sobre tecnologia e gestão">
                <meta name="description" content="Artigos da ABrasil Sistemas sobre tecnologia, gestão, produtividade, desenvolvimento de sites, sistemas e transformação digital para empresas." />
                <meta name="robots" content="index, follow, max-image-preview:large" />
                <meta property="og:title" content="Blog | ABrasil Sistemas" />
                <meta property="og:description" content="Informação prática para usar a tecnologia a favor da sua empresa." />
            </Head>

            <section className="border-b border-slate-200 bg-gradient-to-b from-blue-50 to-white py-16 sm:py-24">
                <div className="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
                    <div className="max-w-3xl">
                        <p className="inline-flex items-center gap-2 text-sm font-bold text-blue-700"><BookOpen className="size-4" /> Blog ABrasil Sistemas</p>
                        <h1 className="mt-4 text-5xl font-bold tracking-[-0.05em] text-balance text-slate-950 sm:text-7xl">Tecnologia aplicada a negócios reais.</h1>
                        <p className="mt-6 text-lg leading-8 text-slate-600 sm:text-xl">Dicas, experiências e ideias sobre gestão, sistemas, sites e tecnologia para ajudar sua empresa a trabalhar melhor.</p>
                    </div>
                    <form onSubmit={submit} className="mt-9 flex max-w-2xl flex-col gap-2 sm:flex-row">
                        <div className="relative flex-1">
                            <Search className="absolute top-3.5 left-3.5 size-5 text-slate-400" />
                            <input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="O que você quer aprender?" className="h-12 w-full rounded-lg border border-slate-300 bg-white pr-4 pl-11 text-sm outline-none transition focus:border-blue-500 focus:ring-3 focus:ring-blue-100" />
                        </div>
                        <button className="h-12 rounded-lg bg-blue-700 px-6 text-sm font-bold text-white hover:bg-blue-800">Buscar artigos</button>
                    </form>
                </div>
            </section>

            <main className="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-12">
                {featured && (
                    <section>
                        <div className="mb-5 flex items-center gap-2 text-sm font-bold text-blue-700"><Sparkles className="size-4" /> Artigo em destaque</div>
                        <Link href={`/blog/${featured.slug}`} className="group grid overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:border-blue-200 hover:shadow-xl lg:grid-cols-[1.05fr_0.95fr]">
                            <div className="min-h-72 bg-slate-100 lg:order-2">
                                {featured.cover_image_url ? (
                                    <img src={featured.cover_image_url} alt="" className="h-full min-h-72 w-full object-cover" />
                                ) : (
                                    <div className="grid h-full min-h-72 place-items-center bg-gradient-to-br from-blue-700 to-cyan-500"><BookOpen className="size-16 text-white/80" /></div>
                                )}
                            </div>
                            <div className="flex flex-col justify-center p-7 sm:p-10 lg:p-12">
                                <div className="flex flex-wrap gap-3 text-xs font-semibold text-slate-500">
                                    {featured.category && <span className="text-blue-700">{featured.category.name}</span>}
                                    <span>{date(featured.published_at)}</span>
                                </div>
                                <h2 className="mt-4 text-3xl font-bold tracking-[-0.03em] text-slate-950 group-hover:text-blue-700 sm:text-4xl">{featured.title}</h2>
                                <p className="mt-4 text-base leading-7 text-slate-600">{featured.excerpt}</p>
                                <span className="mt-7 inline-flex items-center gap-2 text-sm font-bold text-blue-700">Ler artigo <ArrowRight className="size-4" /></span>
                            </div>
                        </Link>
                    </section>
                )}

                <div className={`grid gap-12 ${categories.length || popularPosts.length ? 'lg:grid-cols-[1fr_280px]' : ''} ${featured ? 'mt-16' : ''}`}>
                    <section>
                        <div className="flex items-end justify-between gap-4">
                            <div><p className="text-sm font-bold text-blue-700">Últimas publicações</p><h2 className="mt-1 text-3xl font-bold text-slate-950">Explore nossos artigos</h2></div>
                            {(filters.search || filters.category) && <Link href="/blog" className="text-sm font-bold text-blue-700">Limpar filtros</Link>}
                        </div>
                        {articles.length ? (
                            <div className="mt-8 grid gap-6 md:grid-cols-2">
                                {articles.map((post) => (
                                    <article key={post.id} className="group overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:-translate-y-1 hover:border-blue-200 hover:shadow-lg">
                                        {post.cover_image_url ? <img src={post.cover_image_url} alt="" className="h-48 w-full object-cover" /> : <div className="grid h-40 place-items-center bg-slate-100"><BookOpen className="size-9 text-slate-300" /></div>}
                                        <div className="p-6">
                                            <div className="flex flex-wrap gap-3 text-xs font-semibold text-slate-500">{post.category && <span className="text-blue-700">{post.category.name}</span>}<span>{date(post.published_at)}</span></div>
                                            <h3 className="mt-3 text-xl font-bold leading-7 text-slate-950 group-hover:text-blue-700"><Link href={`/blog/${post.slug}`}>{post.title}</Link></h3>
                                            <p className="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">{post.excerpt}</p>
                                            <Link href={`/blog/${post.slug}`} className="mt-5 inline-flex items-center gap-2 text-sm font-bold text-blue-700">Ler artigo <ArrowRight className="size-4" /></Link>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        ) : <div className="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-10 text-center text-slate-600">Nenhum artigo encontrado para essa busca.</div>}
                        <div className="mt-10 flex flex-wrap gap-2">
                            {posts.links.map((link, index) => link.url && <Link key={index} href={link.url} preserveScroll className={`rounded-lg border px-3 py-2 text-sm font-semibold ${link.active ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 text-slate-600 hover:bg-slate-50'}`} dangerouslySetInnerHTML={{ __html: link.label }} />)}
                        </div>
                    </section>

                    {(categories.length > 0 || popularPosts.length > 0) && (
                        <aside className="space-y-9">
                            {categories.length > 0 && <section><h2 className="font-bold text-slate-950">Categorias</h2><div className="mt-4 space-y-1"><Link href="/blog" className={`flex justify-between rounded-lg px-3 py-2.5 text-sm ${!filters.category ? 'bg-blue-50 font-bold text-blue-700' : 'text-slate-600 hover:bg-slate-50'}`}><span>Todos os artigos</span></Link>{categories.map((category) => <Link key={category.id} href={`/blog?category=${category.slug}`} className={`flex justify-between rounded-lg px-3 py-2.5 text-sm ${filters.category === category.slug ? 'bg-blue-50 font-bold text-blue-700' : 'text-slate-600 hover:bg-slate-50'}`}><span>{category.name}</span><span className="text-slate-400">{category.posts_count}</span></Link>)}</div></section>}
                            {popularPosts.length > 0 && <section><h2 className="font-bold text-slate-950">Mais lidos</h2><div className="mt-4 divide-y divide-slate-200">{popularPosts.map((post) => <Link key={post.id} href={`/blog/${post.slug}`} className="block py-4 first:pt-0"><span className="text-sm font-semibold leading-5 text-slate-800 hover:text-blue-700">{post.title}</span><span className="mt-2 flex items-center gap-1 text-xs text-slate-400"><Eye className="size-3" />{post.views} visualizações</span></Link>)}</div></section>}
                        </aside>
                    )}
                </div>
            </main>

            {!auth.user && (
                <section id="cadastro" className="scroll-mt-24 border-y border-slate-200 bg-slate-50 py-20 sm:py-24">
                    <div className="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-[0.85fr_1.15fr] lg:px-12">
                        <div>
                            <div className="grid size-12 place-items-center rounded-xl bg-blue-100 text-blue-700">
                                <UserPlus className="size-6" />
                            </div>
                            <p className="mt-6 text-sm font-bold text-blue-700">Participe do Blog</p>
                            <h2 className="mt-3 text-4xl font-bold tracking-[-0.04em] text-slate-950 sm:text-5xl">Crie sua conta gratuitamente.</h2>
                            <p className="mt-5 max-w-xl text-base leading-7 text-slate-600">Cadastre-se para comentar nos artigos e participar das conversas sobre tecnologia e negócios.</p>
                            <p className="mt-5 text-sm text-slate-500">Já possui uma conta? <Link href="/login" className="font-bold text-blue-700">Entrar</Link></p>
                        </div>

                        <Form action="/register" method="post" options={{ preserveScroll: true }} resetOnSuccess={['password', 'password_confirmation']} disableWhileProcessing className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                            {({ processing, errors }) => (
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <label className="text-sm font-semibold text-slate-700 sm:col-span-2">
                                        Nome
                                        <input name="name" type="text" required autoComplete="name" placeholder="Seu nome completo" className="mt-2 h-11 w-full rounded-lg border border-slate-300 px-3 font-normal outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100" />
                                        <InputError message={errors.name} className="mt-2" />
                                    </label>
                                    <label className="text-sm font-semibold text-slate-700 sm:col-span-2">
                                        E-mail
                                        <input name="email" type="email" required autoComplete="email" placeholder="voce@exemplo.com" className="mt-2 h-11 w-full rounded-lg border border-slate-300 px-3 font-normal outline-none focus:border-blue-500 focus:ring-3 focus:ring-blue-100" />
                                        <InputError message={errors.email} className="mt-2" />
                                    </label>
                                    <label className="text-sm font-semibold text-slate-700">
                                        Senha
                                        <PasswordInput name="password" required autoComplete="new-password" placeholder="Crie uma senha" className="mt-2 h-11 w-full rounded-lg border-slate-300 px-3 font-normal focus-visible:border-blue-500 focus-visible:ring-blue-100" />
                                        <InputError message={errors.password} className="mt-2" />
                                    </label>
                                    <label className="text-sm font-semibold text-slate-700">
                                        Confirmar senha
                                        <PasswordInput name="password_confirmation" required autoComplete="new-password" placeholder="Repita a senha" className="mt-2 h-11 w-full rounded-lg border-slate-300 px-3 font-normal focus-visible:border-blue-500 focus-visible:ring-blue-100" />
                                        <InputError message={errors.password_confirmation} className="mt-2" />
                                    </label>
                                    <button type="submit" disabled={processing} className="inline-flex h-12 items-center justify-center gap-2 rounded-lg bg-blue-700 px-6 text-sm font-bold text-white hover:bg-blue-800 disabled:opacity-60 sm:col-span-2">
                                        <UserPlus className="size-4" />
                                        {processing ? 'Criando conta...' : 'Criar minha conta'}
                                    </button>
                                    <p className="text-center text-xs leading-5 text-slate-500 sm:col-span-2">Ao se cadastrar, você concorda em usar o espaço de comentários de forma respeitosa.</p>
                                </div>
                            )}
                        </Form>
                    </div>
                </section>
            )}
        </BlogPublicLayout>
    );
}
