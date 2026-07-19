import { Head, Link } from '@inertiajs/react';
import { BookOpen, Download, LibraryBig } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import Heading from '@/components/heading';

type LibraryItem = {
    id: number;
    granted_at: string;
    ebook: {
        id: number;
        volume_slug: string;
        title: string;
        description: string | null;
        version: string;
        status: string;
        published_at: string | null;
    };
};

export default function EbookLibrary({ items }: { items: LibraryItem[] }) {
    return (
        <>
            <Head title="Minha biblioteca" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Minha biblioteca"
                    description="Seus e-books adquiridos, downloads e versões atualizadas."
                />

                {items.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="flex min-h-72 flex-col items-center justify-center text-center">
                            <LibraryBig className="size-12 text-muted-foreground" />
                            <h2 className="mt-5 text-xl font-semibold">
                                Sua biblioteca está vazia
                            </h2>
                            <p className="mt-2 max-w-md text-sm text-muted-foreground">
                                Quando você adquirir um e-book, ele aparecerá
                                aqui para download e futuras atualizações.
                            </p>
                            <Button asChild className="mt-6">
                                <Link href="/conhecimento#trilhas">
                                    <BookOpen className="size-4" />
                                    Conhecer os volumes
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {items.map((item) => (
                            <Card key={item.id}>
                                <CardHeader>
                                    <div className="flex items-center justify-between gap-3">
                                        <Badge variant="secondary">
                                            Versão {item.ebook.version}
                                        </Badge>
                                        <Badge variant="outline">
                                            Disponível
                                        </Badge>
                                    </div>
                                    <CardTitle className="pt-3">
                                        {item.ebook.title}
                                    </CardTitle>
                                    <CardDescription>
                                        {item.ebook.description}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="flex gap-2">
                                    <Button asChild>
                                        <a
                                            href={`/minha-biblioteca/${item.ebook.id}/download`}
                                        >
                                            <Download className="size-4" />
                                            Baixar e-book
                                        </a>
                                    </Button>
                                    <Button asChild variant="outline">
                                        <Link
                                            href={`/conhecimento/volumes/${item.ebook.volume_slug}`}
                                        >
                                            Ver volume
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
