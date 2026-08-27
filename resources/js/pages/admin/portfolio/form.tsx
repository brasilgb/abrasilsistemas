import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link, useForm } from '@inertiajs/react';
import { ImagePlus, LoaderCircle, Upload } from 'lucide-react';
import { type DragEvent, useRef, useState } from 'react';

type PortfolioItem = {
    id: number;
    title: string;
    description: string;
    screenshot_url: string;
    site_url: string | null;
    sort_order: number;
    is_published: boolean;
};

export default function PortfolioForm({
    item,
}: {
    item: PortfolioItem | null;
}) {
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const form = useForm({
        title: item?.title ?? '',
        description: item?.description ?? '',
        screenshot_url: item?.screenshot_url ?? '',
        site_url: item?.site_url ?? '',
        sort_order: item?.sort_order ?? 0,
        is_published: item?.is_published ?? true,
    });

    const uploadScreenshot = async (file: File) => {
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
            const response = await fetch('/portfolio/images', {
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

            form.setData('screenshot_url', result.url);
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
        item
            ? form.put(`/portfolio/${item.id}`)
            : form.post('/portfolio');
    };

    const field =
        'mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm';

    return (
        <>
            <Head title={item ? 'Editar trabalho' : 'Novo trabalho'} />
            <div className="space-y-6 p-4">
                <Heading
                    title={item ? 'Editar trabalho' : 'Novo trabalho'}
                    description="Sites e projetos exibidos como prova de trabalho na página inicial."
                />
                <form onSubmit={submit}>
                    <Card>
                        <CardContent className="grid gap-5 pt-6">
                            <label className="text-sm font-medium">
                                Título
                                <input
                                    className={field}
                                    placeholder="Ex.: Site institucional — Empresa XYZ"
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    required
                                    maxLength={150}
                                />
                                <Err text={form.errors.title} />
                            </label>
                            <label className="text-sm font-medium">
                                Descrição
                                <textarea
                                    className={field}
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    rows={3}
                                    maxLength={500}
                                />
                                <Err text={form.errors.description} />
                            </label>
                            <div className="text-sm font-medium">
                                Print do site
                                <ImageDropzone
                                    uploading={uploading}
                                    preview={form.data.screenshot_url}
                                    onFile={uploadScreenshot}
                                />
                                {uploadError && (
                                    <p className="mt-2 text-xs text-destructive">
                                        {uploadError}
                                    </p>
                                )}
                                <Err text={form.errors.screenshot_url} />
                            </div>
                            <div className="grid gap-5 md:grid-cols-3">
                                <label className="text-sm font-medium md:col-span-2">
                                    Link do site (opcional)
                                    <input
                                        type="url"
                                        className={field}
                                        placeholder="https://"
                                        value={form.data.site_url}
                                        onChange={(e) =>
                                            form.setData(
                                                'site_url',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <Err text={form.errors.site_url} />
                                </label>
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
                            </div>
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
                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href="/portfolio">Cancelar</Link>
                                </Button>
                                <Button disabled={form.processing}>
                                    Salvar trabalho
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
                className={`relative flex min-h-40 w-full items-center justify-center overflow-hidden rounded-lg border-2 border-dashed px-5 py-5 text-center transition ${
                    dragging
                        ? 'border-primary bg-primary/10'
                        : 'border-border bg-muted/30 hover:border-primary/50 hover:bg-muted/50'
                } disabled:cursor-wait disabled:opacity-70`}
            >
                {preview && (
                    <img
                        src={preview}
                        alt="Prévia do print"
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
                        Enviar print do site
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
