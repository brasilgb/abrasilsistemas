import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
type Category = {
    id: number;
    name: string;
    description?: string;
    posts_count: number;
};
export default function Categories({ categories }: { categories: Category[] }) {
    const form = useForm({ name: '', description: '' });
    return (
        <>
            <Head title="Categorias do Blog" />
            <div className="space-y-6 p-4">
                <div className="flex justify-between">
                    <Heading
                        title="Categorias"
                        description="Organize os artigos por assunto."
                    />
                    <Button asChild variant="outline">
                        <Link href="/admin/blog/posts">Voltar</Link>
                    </Button>
                </div>
                <Card>
                    <CardContent className="pt-6">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/admin/blog/categories', {
                                    onSuccess: () => form.reset(),
                                });
                            }}
                            className="grid gap-3 sm:grid-cols-[1fr_2fr_auto]"
                        >
                            <input
                                className="rounded-md border bg-background px-3 py-2"
                                placeholder="Nome"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                                required
                            />
                            <input
                                className="rounded-md border bg-background px-3 py-2"
                                placeholder="Descrição opcional"
                                value={form.data.description}
                                onChange={(e) =>
                                    form.setData('description', e.target.value)
                                }
                            />
                            <Button>Adicionar</Button>
                        </form>
                        <div className="mt-6 divide-y rounded-md border">
                            {categories.map((c) => (
                                <div
                                    key={c.id}
                                    className="flex items-center justify-between p-4"
                                >
                                    <div>
                                        <strong>{c.name}</strong>
                                        <p className="text-sm text-muted-foreground">
                                            {c.description || 'Sem descrição'} ·{' '}
                                            {c.posts_count} artigos
                                        </p>
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() =>
                                            confirm(`Excluir ${c.name}?`) &&
                                            router.delete(
                                                `/admin/blog/categories/${c.id}`,
                                            )
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
