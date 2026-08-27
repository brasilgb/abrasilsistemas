import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link, useForm } from '@inertiajs/react';
import { ImagePlus, LoaderCircle, Upload } from 'lucide-react';
import { type DragEvent, useRef, useState } from 'react';

type Testimonial = {
    id: number;
    author_name: string;
    author_role: string | null;
    photo_url: string | null;
    quote: string;
    sort_order: number;
    is_published: boolean;
};

export default function TestimonialForm({
    testimonial,
}: {
    testimonial: Testimonial | null;
}) {
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const form = useForm({
        author_name: testimonial?.author_name ?? '',
        author_role: testimonial?.author_role ?? '',
        photo_url: testimonial?.photo_url ?? '',
        quote: testimonial?.quote ?? '',
        sort_order: testimonial?.sort_order ?? 0,
        is_published: testimonial?.is_published ?? true,
    });

    const uploadPhoto = async (file: File) => {
        if (!file.type.startsWith('image/')) {
            setUploadError('Selecione uma imagem JPG, PNG ou WebP.');
            return;
        }

        setUploading(true);
        setUploadError(null);
        const data = new FormData();
        data.append('image', file);

        try {
            const csrfToken = document
                .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.getAttribute('content');
            const response = await fetch('/testimonials/images', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                body: data,
            });
            const result = (await response.json()) as {
                url?: string;
                message?: string;
                errors?: { image?: string[] };
            };

            if (!response.ok || !result.url) {
                throw new Error(
                    result.errors?.image?.[0] ??
                        result.message ??
                        'Não foi possível enviar a imagem.',
                );
            }

            form.setData('photo_url', result.url);
        } catch (error) {
            setUploadError(
                error instanceof Error
                    ? error.message
                    : 'Não foi possível enviar a imagem.',
            );
        } finally {
            setUploading(false);
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        testimonial
            ? form.put(`/testimonials/${testimonial.id}`)
            : form.post('/testimonials');
    };

    const field =
        'mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm';

    return (
        <>
            <Head title={testimonial ? 'Editar depoimento' : 'Novo depoimento'} />
            <div className="space-y-6 p-4">
                <Heading
                    title={testimonial ? 'Editar depoimento' : 'Novo depoimento'}
                    description="Depoimentos aparecem na página inicial do site, na ordem definida abaixo."
                />
                <form onSubmit={submit}>
                    <Card>
                        <CardContent className="grid gap-5 pt-6">
                            <div className="grid gap-5 md:grid-cols-2">
                                <label className="text-sm font-medium">
                                    Nome
                                    <input
                                        className={field}
                                        value={form.data.author_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'author_name',
                                                e.target.value,
                                            )
                                        }
                                        required
                                        maxLength={120}
                                    />
                                    <Err text={form.errors.author_name} />
                                </label>
                                <label className="text-sm font-medium">
                                    Cargo / empresa
                                    <input
                                        className={field}
                                        placeholder="Ex.: Diretor, Assistência Técnica XYZ"
                                        value={form.data.author_role}
                                        onChange={(e) =>
                                            form.setData(
                                                'author_role',
                                                e.target.value,
                                            )
                                        }
                                        maxLength={150}
                                    />
                                    <Err text={form.errors.author_role} />
                                </label>
                            </div>
                            <label className="text-sm font-medium">
                                Depoimento
                                <textarea
                                    className={field}
                                    value={form.data.quote}
                                    onChange={(e) =>
                                        form.setData('quote', e.target.value)
                                    }
                                    required
                                    rows={5}
                                    maxLength={1000}
                                />
                                <Err text={form.errors.quote} />
                            </label>
                            <div className="grid gap-5 md:grid-cols-2">
                                <div className="text-sm font-medium">
                                    Foto (opcional)
                                    <ImageDropzone
                                        uploading={uploading}
                                        preview={form.data.photo_url}
                                        onFile={uploadPhoto}
                                    />
                                    {uploadError && (
                                        <p className="mt-2 text-xs text-destructive">
                                            {uploadError}
                                        </p>
                                    )}
                                    <Err text={form.errors.photo_url} />
                                </div>
                                <div className="grid gap-5">
                                    <label className="text-sm font-medium">
                                        Ordem de exibição
                                        <input
                                            type="number"
                                            className={field}
                                            value={form.data.sort_order}
                                            onChange={(e) =>
                                                form.setData(
                                                    'sort_order',
                                                    Number(e.target.value),
                                                )
                                            }
                                            min={0}
                                        />
                                        <span className="mt-1 block text-xs font-normal text-muted-foreground">
                                            Menor número aparece primeiro.
                                        </span>
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={form.data.is_published}
                                            onChange={(e) =>
                                                form.setData(
                                                    'is_published',
                                                    e.target.checked,
                                                )
                                            }
                                        />
                                        Publicado no site
                                    </label>
                                </div>
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/testimonials">Cancelar</Link>
                                </Button>
                                <Button disabled={form.processing}>
                                    Salvar depoimento
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

function ImageDropzone({
    uploading,
    preview,
    onFile,
}: {
    uploading: boolean;
    preview?: string;
    onFile: (file: File) => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const receiveFiles = (files: FileList | null) => {
        const file = files?.[0];
        if (file) onFile(file);
    };
    const drop = (event: DragEvent<HTMLButtonElement>) => {
        event.preventDefault();
        setDragging(false);
        receiveFiles(event.dataTransfer.files);
    };

    return (
        <div className="mt-2">
            <input
                ref={inputRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                className="hidden"
                onChange={(event) => {
                    receiveFiles(event.target.files);
                    event.target.value = '';
                }}
            />
            <button
                type="button"
                disabled={uploading}
                onClick={() => inputRef.current?.click()}
                onDragEnter={(event) => {
                    event.preventDefault();
                    setDragging(true);
                }}
                onDragOver={(event) => event.preventDefault()}
                onDragLeave={() => setDragging(false)}
                onDrop={drop}
                className={`relative flex min-h-28 w-full items-center justify-center overflow-hidden rounded-lg border-2 border-dashed px-5 py-5 text-center transition ${
                    dragging
                        ? 'border-primary bg-primary/10'
                        : 'border-border bg-muted/30 hover:border-primary/50 hover:bg-muted/50'
                } disabled:cursor-wait disabled:opacity-70`}
            >
                {preview && (
                    <img
                        src={preview}
                        alt="Prévia da foto"
                        className="absolute inset-0 size-full object-cover opacity-15"
                    />
                )}
                <span className="relative flex flex-col items-center">
                    {uploading ? (
                        <LoaderCircle className="size-6 animate-spin text-primary" />
                    ) : preview ? (
                        <ImagePlus className="size-6 text-primary" />
                    ) : (
                        <Upload className="size-6 text-muted-foreground" />
                    )}
                    <span className="mt-2 text-sm font-semibold">
                        Enviar foto
                    </span>
                    <span className="mt-1 text-xs font-normal text-muted-foreground">
                        {uploading
                            ? 'Enviando imagem...'
                            : 'Arraste ou clique para selecionar'}
                    </span>
                </span>
            </button>
        </div>
    );
}
