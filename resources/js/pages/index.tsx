import { Head, Link } from '@inertiajs/react';
import { BarChart3, MessageSquare, Plus, Users } from 'lucide-react';
import { Head, Link, router } from '@inertiajs/react';
import { BarChart3, MessageSquare, Plus, Users, MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { cn } from '@/lib/utils';
import { index } from '@/routes/blog';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { create, edit, index } from '@/routes/blog';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Pagination, PaginationContent, PaginationItem, PaginationLink } from '@/components/ui/pagination';
import type { Paginated } from '@/types/paginated';

type Props = {
    posts: Paginated<Post>;
    metrics: {
        posts: number;
        comments: number;
        users: number;
        posts_today: number;
    };
};

export default function BlogIndex({ metrics }: Props) {
type Post = {
    id: number;
    title: string;
    status: 'published' | 'draft';
    created_at: string;
};

export default function BlogIndex({ posts, metrics }: Props) {
    const [activeTab, setActiveTab] = useState<'posts' | 'comments' | 'users' | 'metrics'>('posts');

    return (
        <>
            <Head title="Blog" />

            <div className="space-y-6 p-4">
                <div className="flex flex-col gap-4">
                    <Heading
                        title="Administração do Blog"
                        description="Gerencie postagens, comentários e usuários."
                    />

                    <div className="grid w-full gap-2 rounded-lg border bg-muted p-1 sm:inline-grid sm:w-auto sm:grid-cols-4">
                        <button
                            type="button"
                            onClick={() => setActiveTab('posts')}
                            className={cn(
                                'inline-flex h-9 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium transition-colors',
                                activeTab === 'posts'
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Plus className="size-4" />
                            Postagens
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('comments')}
                            className={cn(
                                'inline-flex h-9 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium transition-colors',
                                activeTab === 'comments'
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <MessageSquare className="size-4" />
                            Comentários
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('users')}
                            className={cn(
                                'inline-flex h-9 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium transition-colors',
                                activeTab === 'users'
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <Users className="size-4" />
                            Usuários
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('metrics')}
                            className={cn(
                                'inline-flex h-9 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium transition-colors',
                                activeTab === 'metrics'
                                    ? 'bg-background text-foreground shadow-xs'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            <BarChart3 className="size-4" />
                            Métricas
                        </button>
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                    <div>
                        <Heading
                            title="Administração do Blog"
                            description="Gerencie postagens, comentários e usuários."
                        />
                    </div>
                    <Button asChild>
                        <Link href={create()}>Nova postagem</Link>
                    </Button>
                </div>

                <div className="grid w-full gap-2 rounded-lg border bg-muted p-1 sm:inline-grid sm:w-auto sm:grid-cols-4">
                    <button
                        type="button"
                        onClick={() => setActiveTab('posts')}
                        className={cn(
                            'inline-flex h-9 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium transition-colors',
                            activeTab === 'posts'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <Plus className="size-4" />
                        Postagens
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('comments')}
                        className={cn(
                            'inline-flex h-9 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium transition-colors',
                            activeTab === 'comments'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <MessageSquare className="size-4" />
                        Comentários
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('users')}
                        className={cn(
                            'inline-flex h-9 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium transition-colors',
                            activeTab === 'users'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <Users className="size-4" />
                        Usuários
                    </button>
                    <button
                        type="button"
                        onClick={() => setActiveTab('metrics')}
                        className={cn(
                            'inline-flex h-9 items-center justify-center gap-2 rounded-md px-4 text-sm font-medium transition-colors',
                            activeTab === 'metrics'
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground',
                        )}
                    >
                        <BarChart3 className="size-4" />
                        Métricas
                    </button>
                </div>

                {activeTab === 'metrics' && (
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Total de Posts</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-3xl font-bold">{metrics.posts}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Posts Hoje</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-3xl font-bold">{metrics.posts_today}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Total de Comentários</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-3xl font-bold">{metrics.comments}</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Total de Usuários</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-3xl font-bold">{metrics.users}</p>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {(activeTab === 'posts' || activeTab === 'comments' || activeTab === 'users') && (
                {activeTab === 'posts' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Postagens</CardTitle>
                            <CardDescription>
                                Todas as postagens do blog.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Título</TableHead>
                                        <TableHead className="hidden sm:table-cell">
                                            Status
                                        </TableHead>
                                        <TableHead className="hidden sm:table-cell">
                                            Criado em
                                        </TableHead>
                                        <TableHead>
                                            <span className="sr-only">Ações</span>
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {posts.data.map((post) => (
                                        <TableRow key={post.id}>
                                            <TableCell className="font-medium">
                                                {post.title}
                                            </TableCell>
                                            <TableCell className="hidden sm:table-cell">
                                                <Badge variant="outline">
                                                    {post.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="hidden sm:table-cell">
                                                {post.created_at}
                                            </TableCell>
                                            <TableCell>
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button
                                                            aria-haspopup="true"
                                                            size="icon"
                                                            variant="ghost"
                                                        >
                                                            <MoreHorizontal className="h-4 w-4" />
                                                            <span className="sr-only">
                                                                Toggle menu
                                                            </span>
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuLabel>
                                                            Ações
                                                        </DropdownMenuLabel>
                                                        <DropdownMenuItem>
                                                            Editar
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem>
                                                            Excluir
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {(activeTab === 'comments' || activeTab === 'users') && (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {activeTab === 'posts' && 'Postagens'}
                                {activeTab === 'comments' && 'Comentários'}
                                {activeTab === 'users' && 'Usuários'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-center text-muted-foreground py-12">Em construção.</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

BlogIndex.layout = {
    breadcrumbs: [
        {
            title: 'Blog',
            href: index(),
        },
    ],
};