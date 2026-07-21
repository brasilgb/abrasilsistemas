import BlogPublicLayout from '@/components/blog-public-layout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, MessageCircle } from 'lucide-react';
import { FormEvent, useState } from 'react';
import type { User } from '@/types';
type Comment = {
    id: number;
    body: string;
    created_at: string;
    user: { name: string };
};
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
    relatedPosts: {
        id: number;
        title: string;
        slug: string;
        excerpt: string;
    }[];
};
const date = (value: string) =>
    new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long' }).format(
        new Date(value),
    );

export default function BlogShow({ post, relatedPosts }: Props) {
    const { auth } = usePage<{ auth: { user: User | null } }>().props;
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);
    const comment = (e: FormEvent) => {
        e.preventDefault();
        setSending(true);
        router.post(
            `/blog/${post.slug}/comentarios`,
            { body },
            { onSuccess: () => setBody(''), onFinish: () => setSending(false) },
        );
    };
    return (
        <BlogPublicLayout>
            <Head title={post.title}>
                <meta name="description" content={post.excerpt} />
            </Head>
            <main className="mx-auto max-w-4xl px-5 py-14 sm:px-8">
                <Link
                    href="/blog"
                    className="inline-flex items-center gap-2 text-sm text-sky-300"
                >
                    <ArrowLeft className="size-4" /> Voltar ao blog
                </Link>
                <article className="mt-10">
                    <div className="flex flex-wrap gap-3 text-sm text-slate-400">
                        {post.category && (
                            <Link
                                href={`/blog?category=${post.category.slug}`}
                                className="text-sky-300"
                            >
                                {post.category.name}
                            </Link>
                        )}
                        <span>{date(post.published_at)}</span>
                        <span>Por {post.author.name}</span>
                    </div>
                    <h1 className="mt-5 text-4xl font-semibold tracking-tight sm:text-6xl">
                        {post.title}
                    </h1>
                    <p className="mt-6 text-xl leading-8 text-slate-400">
                        {post.excerpt}
                    </p>
                    {post.cover_image_url && (
                        <img
                            src={post.cover_image_url}
                            alt=""
                            className="mt-10 max-h-[500px] w-full rounded-3xl object-cover"
                        />
                    )}
                    <div className="mt-12 text-lg leading-8 whitespace-pre-wrap text-slate-200">
                        {post.body}
                    </div>
                </article>
                <section className="mt-16 border-t border-white/10 pt-10">
                    <h2 className="flex items-center gap-2 text-2xl font-bold">
                        <MessageCircle className="size-6" /> Comentários (
                        {post.comments.length})
                    </h2>
                    {auth.user ? (
                        <form onSubmit={comment} className="mt-6">
                            <textarea
                                value={body}
                                onChange={(e) => setBody(e.target.value)}
                                minLength={3}
                                maxLength={2000}
                                required
                                rows={4}
                                placeholder="Escreva seu comentário"
                                className="w-full rounded-xl border border-white/10 bg-white/5 p-4 outline-none focus:border-sky-400"
                            />
                            <button
                                disabled={sending}
                                className="mt-3 rounded-xl bg-sky-400 px-5 py-2.5 font-bold text-slate-950 disabled:opacity-50"
                            >
                                Enviar para moderação
                            </button>
                        </form>
                    ) : (
                        <p className="mt-5 rounded-xl border border-white/10 p-5 text-slate-400">
                            <Link
                                href={`/login`}
                                className="font-bold text-sky-300"
                            >
                                Entre na sua conta
                            </Link>{' '}
                            para comentar.
                        </p>
                    )}
                    <div className="mt-8 space-y-4">
                        {post.comments.map((comment) => (
                            <div
                                key={comment.id}
                                className="rounded-xl border border-white/8 bg-white/[.03] p-5"
                            >
                                <div className="flex justify-between text-sm">
                                    <strong>{comment.user.name}</strong>
                                    <span className="text-slate-500">
                                        {date(comment.created_at)}
                                    </span>
                                </div>
                                <p className="mt-3 whitespace-pre-wrap text-slate-300">
                                    {comment.body}
                                </p>
                            </div>
                        ))}
                    </div>
                </section>
                {relatedPosts.length > 0 && (
                    <section className="mt-16">
                        <h2 className="text-2xl font-bold">
                            Artigos relacionados
                        </h2>
                        <div className="mt-5 grid gap-4 sm:grid-cols-3">
                            {relatedPosts.map((item) => (
                                <Link
                                    key={item.id}
                                    href={`/blog/${item.slug}`}
                                    className="rounded-xl border border-white/10 p-5 hover:border-sky-300/30"
                                >
                                    <strong>{item.title}</strong>
                                    <p className="mt-2 line-clamp-3 text-sm text-slate-400">
                                        {item.excerpt}
                                    </p>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
            </main>
        </BlogPublicLayout>
    );
}
