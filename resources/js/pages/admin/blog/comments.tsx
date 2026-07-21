import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link, router } from '@inertiajs/react';
import { Check, Trash2, X } from 'lucide-react';
type Comment = {
    id: number;
    body: string;
    status: string;
    created_at: string;
    user: { name: string; email: string };
    post: { title: string; slug: string };
};
export default function Comments({
    comments,
}: {
    comments: { data: Comment[] };
}) {
    return (
        <>
            <Head title="Comentários do Blog" />
            <div className="space-y-6 p-4">
                <div className="flex justify-between">
                    <Heading
                        title="Comentários"
                        description="Aprove, rejeite ou exclua comentários."
                    />
                    <Button asChild variant="outline">
                        <Link href="/admin/blog/posts">Voltar</Link>
                    </Button>
                </div>
                <Card>
                    <CardContent className="divide-y pt-6">
                        {comments.data.map((c) => (
                            <div key={c.id} className="py-5">
                                <div className="flex flex-col justify-between gap-3 sm:flex-row">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <strong>{c.user.name}</strong>
                                            <Badge variant="outline">
                                                {c.status === 'pending'
                                                    ? 'Pendente'
                                                    : c.status === 'approved'
                                                      ? 'Aprovado'
                                                      : 'Rejeitado'}
                                            </Badge>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {c.user.email} · em{' '}
                                            <a
                                                target="_blank"
                                                href={`/blog/${c.post.slug}`}
                                                className="underline"
                                            >
                                                {c.post.title}
                                            </a>
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        {c.status !== 'approved' && (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    router.patch(
                                                        `/admin/blog/comments/${c.id}/approve`,
                                                    )
                                                }
                                            >
                                                <Check />
                                                Aprovar
                                            </Button>
                                        )}
                                        {c.status !== 'rejected' && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    router.patch(
                                                        `/admin/blog/comments/${c.id}/reject`,
                                                    )
                                                }
                                            >
                                                <X />
                                                Rejeitar
                                            </Button>
                                        )}
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() =>
                                                confirm(
                                                    'Excluir este comentário?',
                                                ) &&
                                                router.delete(
                                                    `/admin/blog/comments/${c.id}`,
                                                )
                                            }
                                        >
                                            <Trash2 />
                                        </Button>
                                    </div>
                                </div>
                                <p className="mt-3 text-sm whitespace-pre-wrap">
                                    {c.body}
                                </p>
                            </div>
                        ))}
                        {!comments.data.length && (
                            <p className="py-10 text-center text-muted-foreground">
                                Nenhum comentário.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
