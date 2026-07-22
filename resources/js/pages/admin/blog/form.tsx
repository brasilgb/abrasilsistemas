import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link, useForm } from '@inertiajs/react';
import { ImagePlus, LoaderCircle, Upload } from 'lucide-react';
import { type DragEvent, useRef, useState } from 'react';
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
    const [uploading, setUploading] = useState<'cover' | 'body' | null>(null);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const bodyRef = useRef<HTMLTextAreaElement>(null);
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
    const uploadImage = async (file: File, target: 'cover' | 'body') => {
        if (!file.type.startsWith('image/')) {
            setUploadError('Selecione uma imagem JPG, PNG ou WebP.');
            return;
        }

        setUploading(target);
        setUploadError(null);
        const data = new FormData();
        data.append('image', file);

        try {
            const csrfToken = document
                .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.getAttribute('content');
            const response = await fetch('/admin/blog/images', {
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

            if (target === 'cover') {
                form.setData('cover_image_url', result.url);
            } else {
                const textarea = bodyRef.current;
                const imageMarkup = `\n\n![Imagem do artigo](${result.url})\n\n`;
                const cursor = textarea?.selectionStart ?? form.data.body.length;
                form.setData(
                    'body',
                    `${form.data.body.slice(0, cursor)}${imageMarkup}${form.data.body.slice(cursor)}`,
                );
            }
        } catch (error) {
            setUploadError(
                error instanceof Error
                    ? error.message
                    : 'Não foi possível enviar a imagem.',
            );
        } finally {
            setUploading(null);
        }
    };
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
                    description="Publique conteúdos sobre tecnologia, gestão, produtos e dicas para empresas."
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
                                    ref={bodyRef}
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
                                <span className="mt-1 block text-xs font-normal text-muted-foreground">
                                    Use ## para subtítulos, ### para seções e -
                                    para itens de uma lista. Separe os
                                    parágrafos com uma linha vazia.
                                </span>
                                <textarea
                                    className={field}
                                    value={form.data.body}
                                    onChange={(e) =>
                                        form.setData('body', e.target.value)
                                    }
                                    required
                                    rows={16}
                                    placeholder={
                                        'Comece o artigo com uma introdução...\n\n## Primeiro tópico\n\nDesenvolva a ideia principal.\n\n- Dica prática\n- Outro ponto importante'
                                    }
                                />
                                <ImageDropzone
                                    label="Inserir imagem no artigo"
                                    description="Arraste uma imagem para cá ou clique para selecionar. Ela será inserida na posição do cursor."
                                    uploading={uploading === 'body'}
                                    onFile={(file) =>
                                        uploadImage(file, 'body')
                                    }
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
                                <div className="text-sm font-medium">
                                    Imagem de capa
                                    <ImageDropzone
                                        label="Enviar imagem de capa"
                                        description="Arraste ou selecione uma imagem de até 5 MB."
                                        uploading={uploading === 'cover'}
                                        preview={form.data.cover_image_url}
                                        onFile={(file) =>
                                            uploadImage(file, 'cover')
                                        }
                                    />
                                    <span className="mt-3 block text-xs font-normal text-muted-foreground">
                                        Ou informe uma URL externa
                                    </span>
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
                                </div>
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
                            {uploadError && (
                                <p className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                                    {uploadError}
                                </p>
                            )}
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

function ImageDropzone({
    label,
    description,
    uploading,
    preview,
    onFile,
}: {
    label: string;
    description: string;
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
                        alt="Prévia da imagem"
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
                    <span className="mt-2 text-sm font-semibold">{label}</span>
                    <span className="mt-1 text-xs font-normal text-muted-foreground">
                        {uploading ? 'Enviando imagem...' : description}
                    </span>
                </span>
            </button>
        </div>
    );
}
