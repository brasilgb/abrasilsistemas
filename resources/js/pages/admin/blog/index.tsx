import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link, router } from '@inertiajs/react';
import {
    BookOpen,
    Eye,
    ExternalLink,
    Globe,
    MessageSquare,
    Pencil,
    Plus,
    Tags,
    Trash2,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
type Post = {
    id: number;
    title: string;
    slug: string;
    status: string;
    views: number;
    created_at: string;
    category?: { name: string };
    author: { name: string };
};
type Props = {
    posts: {
        data: Post[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    metrics: {
        posts: number;
        published: number;
        pendingComments: number;
        users: number;
    };
};
export default function AdminBlogIndex({ posts, metrics }: Props) {
    const metricCards: [LucideIcon, string, number][] = [
        [BookOpen, 'Artigos', metrics.posts],
        [Eye, 'Publicados', metrics.published],
        [MessageSquare, 'Comentários pendentes', metrics.pendingComments],
        [Users, 'Usuários', metrics.users],
    ];
    const remove = (post: Post) => {
        if (confirm(`Excluir o artigo “${post.title}”?`))
            router.delete(`/admin/blog/posts/${post.id}`);
    };
    return (
        <>
            <Head title="Administração do Blog" />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title="Administração do Blog"
                        description="Gerencie artigos, categorias e comentários."
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <a href="/blog" target="_blank" rel="noreferrer">
                                <Globe />
                                Ir para o site
                            </a>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/admin/blog/categories">
                                <Tags />
                                Categorias
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/admin/blog/comments">
                                <MessageSquare />
                                Comentários
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href="/admin/blog/posts/create">
                                <Plus />
                                Novo artigo
                            </Link>
                        </Button>
                    </div>
                </div>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {metricCards.map(([Icon, label, value]) => (
                        <Card key={String(label)}>
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center justify-between text-sm text-muted-foreground">
                                        {label}
                                    <Icon className="size-4" />
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-3xl font-bold">
                                    {value}
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Artigos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="divide-y rounded-md border">
                            {posts.data.map((post) => (
                                <div
                                    key={post.id}
                                    className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <strong>{post.title}</strong>
                                            <Badge variant="outline">
                                                {post.status === 'published'
                                                    ? 'Publicado'
                                                    : 'Rascunho'}
                                            </Badge>
                                        </div>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {post.category?.name ??
                                                'Sem categoria'}{' '}
                                            · {post.author.name} · {post.views}{' '}
                                            visualizações
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        {post.status === 'published' && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                asChild
                                            >
                                                <a
                                                    href={`/blog/${post.slug}`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <ExternalLink />
                                                    Visualizar artigo
                                                </a>
                                            </Button>
                                        )}
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={`/admin/blog/posts/${post.id}/edit`}
                                            >
                                                <Pencil />
                                                Editar
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() => remove(post)}
                                        >
                                            <Trash2 />
                                            Excluir
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            {!posts.data.length && (
                                <p className="p-10 text-center text-muted-foreground">
                                    Nenhum artigo cadastrado.
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
