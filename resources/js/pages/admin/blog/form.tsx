import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link, useForm } from '@inertiajs/react';
type Category = { id: number; name: string };
type Post = {
    id: number;
    title: string;
    excerpt: string;
    body: string;
    blog_category_id?: number;
    cover_image_url?: string;
    status: 'draft' | 'published';
    published_at?: string;
    featured: boolean;
};
export default function BlogForm({
    categories,
    post,
}: {
    categories: Category[];
    post: Post | null;
}) {
    const form = useForm({
        title: post?.title ?? '',
        excerpt: post?.excerpt ?? '',
        body: post?.body ?? '',
        blog_category_id: post?.blog_category_id
            ? String(post.blog_category_id)
            : '',
        cover_image_url: post?.cover_image_url ?? '',
        status: post?.status ?? 'draft',
        published_at: post?.published_at?.slice(0, 16) ?? '',
        featured: post?.featured ?? false,
    });
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post
            ? form.put(`/admin/blog/posts/${post.id}`)
            : form.post('/admin/blog/posts');
    };
    const field =
        'mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm';
    return (
        <>
            <Head title={post ? 'Editar artigo' : 'Novo artigo'} />
            <div className="space-y-6 p-4">
                <Heading
                    title={post ? 'Editar artigo' : 'Novo artigo'}
                    description="O slug da URL é criado automaticamente a partir do título."
                />
                <form onSubmit={submit}>
                    <Card>
                        <CardContent className="grid gap-5 pt-6">
                            <label className="text-sm font-medium">
                                Título
                                <input
                                    className={field}
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    required
                                    maxLength={180}
                                />
                                <Err text={form.errors.title} />
                            </label>
                            <label className="text-sm font-medium">
                                Resumo
                                <textarea
                                    className={field}
                                    value={form.data.excerpt}
                                    onChange={(e) =>
                                        form.setData('excerpt', e.target.value)
                                    }
                                    required
                                    rows={3}
                                    maxLength={500}
                                />
                                <Err text={form.errors.excerpt} />
                            </label>
                            <label className="text-sm font-medium">
                                Conteúdo
                                <textarea
                                    className={field}
                                    value={form.data.body}
                                    onChange={(e) =>
                                        form.setData('body', e.target.value)
                                    }
                                    required
                                    rows={16}
                                />
                                <Err text={form.errors.body} />
                            </label>
                            <div className="grid gap-5 md:grid-cols-2">
                                <label className="text-sm font-medium">
                                    Categoria
                                    <select
                                        className={field}
                                        value={form.data.blog_category_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'blog_category_id',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Sem categoria</option>
                                        {categories.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="text-sm font-medium">
                                    Status
                                    <select
                                        className={field}
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setData(
                                                'status',
                                                e.target.value as
                                                    'draft' | 'published',
                                            )
                                        }
                                    >
                                        <option value="draft">Rascunho</option>
                                        <option value="published">
                                            Publicado
                                        </option>
                                    </select>
                                </label>
                                <label className="text-sm font-medium">
                                    Imagem de capa (URL)
                                    <input
                                        type="url"
                                        className={field}
                                        value={form.data.cover_image_url}
                                        onChange={(e) =>
                                            form.setData(
                                                'cover_image_url',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <Err text={form.errors.cover_image_url} />
                                </label>
                                <label className="text-sm font-medium">
                                    Data de publicação
                                    <input
                                        type="datetime-local"
                                        className={field}
                                        value={form.data.published_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'published_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </label>
                            </div>
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.featured}
                                    onChange={(e) =>
                                        form.setData(
                                            'featured',
                                            e.target.checked,
                                        )
                                    }
                                />{' '}
                                Destacar artigo
                            </label>
                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/admin/blog/posts">
                                        Cancelar
                                    </Link>
                                </Button>
                                <Button disabled={form.processing}>
                                    Salvar artigo
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </>
    );
}
function Err({ text }: { text?: string }) {
    return text ? (
        <span className="mt-1 block text-xs text-destructive">{text}</span>
    ) : null;
}
