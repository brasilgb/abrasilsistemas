import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Quote, Trash2 } from 'lucide-react';

type Testimonial = {
    id: number;
    author_name: string;
    author_role: string | null;
    photo_url: string | null;
    quote: string;
    is_published: boolean;
};

export default function AdminTestimonialsIndex({
    testimonials,
}: {
    testimonials: Testimonial[];
}) {
    const remove = (testimonial: Testimonial) => {
        if (confirm(`Excluir o depoimento de “${testimonial.author_name}”?`))
            router.delete(`/testimonials/${testimonial.id}`);
    };

    return (
        <>
            <Head title="Depoimentos" />
            <div className="space-y-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <Heading
                        title="Depoimentos"
                        description="Depoimentos de clientes exibidos na página inicial do site."
                    />
                    <Button asChild>
                        <Link href="/testimonials/create">
                            <Plus />
                            Novo depoimento
                        </Link>
                    </Button>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Todos os depoimentos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="divide-y rounded-md border">
                            {testimonials.map((testimonial) => (
                                <div
                                    key={testimonial.id}
                                    className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="flex items-start gap-3">
                                        {testimonial.photo_url ? (
                                            <img
                                                src={testimonial.photo_url}
                                                alt={testimonial.author_name}
                                                className="size-10 shrink-0 rounded-full object-cover"
                                            />
                                        ) : (
                                            <span className="grid size-10 shrink-0 place-items-center rounded-full bg-muted text-muted-foreground">
                                                <Quote className="size-4" />
                                            </span>
                                        )}
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <strong>
                                                    {testimonial.author_name}
                                                </strong>
                                                <Badge
                                                    variant={
                                                        testimonial.is_published
                                                            ? 'outline'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {testimonial.is_published
                                                        ? 'Publicado'
                                                        : 'Oculto'}
                                                </Badge>
                                            </div>
                                            {testimonial.author_role && (
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {testimonial.author_role}
                                                </p>
                                            )}
                                            <p className="mt-1 line-clamp-2 max-w-xl text-sm text-muted-foreground">
                                                “{testimonial.quote}”
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link
                                                href={`/testimonials/${testimonial.id}/edit`}
                                            >
                                                <Pencil />
                                                Editar
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() => remove(testimonial)}
                                        >
                                            <Trash2 />
                                            Excluir
                                        </Button>
                                    </div>
                                </div>
                            ))}
                            {!testimonials.length && (
                                <p className="p-10 text-center text-muted-foreground">
                                    Nenhum depoimento cadastrado.
                                </p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
