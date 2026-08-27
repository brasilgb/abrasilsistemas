import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink, Pencil, Plus, Trash2 } from 'lucide-react';

type PortfolioItem = {
    id: number;
    title: string;
    description: string;
    screenshot_url: string;
    site_url: string | null;
    is_published: boolean;
};

export default function AdminPortfolioIndex({
    items,
}: {
    items: PortfolioItem[];
}) {
    const remove = (item: PortfolioItem) => {
        if (confirm(`Excluir o trabalho “${item.title}”?`))
            router.delete(`/portfolio/${item.id}`);
    };

    return (
        <>
            <Head title="Trabalhos recentes" />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title="Trabalhos recentes"
                        description="Sites e projetos exibidos na página inicial como prova de trabalho."
                    />
                    <Button asChild>
                        <Link href="/portfolio/create">
                            <Plus />
                            Novo trabalho
                        </Link>
                    </Button>
                </div>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {items.map((item) => (
                        <Card key={item.id} className="overflow-hidden">
                            <img
                                src={item.screenshot_url}
                                alt={item.title}
                                className="h-40 w-full border-b object-cover object-top"
                            />
                            <CardHeader className="pb-2">
                                <CardTitle className="flex items-center justify-between gap-2 text-base">
                                    <span className="truncate">
                                        {item.title}
                                    </span>
                                    <Badge
                                        variant={
                                            item.is_published
                                                ? 'outline'
                                                : 'secondary'
                                        }
                                    >
                                        {item.is_published
                                            ? 'Publicado'
                                            : 'Oculto'}
                                    </Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="line-clamp-2 text-sm text-muted-foreground">
                                    {item.description}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {item.site_url && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <a
                                                href={item.site_url}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <ExternalLink />
                                                Ver site
                                            </a>
                                        </Button>
                                    )}
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        asChild
                                    >
                                        <Link
                                            href={`/portfolio/${item.id}/edit`}
                                        >
                                            <Pencil />
                                            Editar
                                        </Link>
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        onClick={() => remove(item)}
                                    >
                                        <Trash2 />
                                        Excluir
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                {!items.length && (
                    <p className="rounded-md border p-10 text-center text-muted-foreground">
                        Nenhum trabalho cadastrado.
                    </p>
                )}
            </div>
        </>
    );
}
