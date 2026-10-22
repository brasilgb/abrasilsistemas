import { Head, Link, useForm } from '@inertiajs/react';
import { ImagePlus, LoaderCircle, Upload, X } from 'lucide-react';
import {   useRef, useState } from 'react';
import type {DragEvent, KeyboardEvent} from 'react';
import Heading from '@/components/heading';
import { RichTextEditor } from '@/components/rich-text-editor';
import {
    EditorialAnalysis,
    useEditorialAnalysis,
} from '@/components/seo/editorial-analysis';
import { SeoMetaFields, seoMetaForm } from '@/components/seo/meta-fields';
import type {
    PageOption,
    SeoMetaForm,
    SeoMetaRecord,
} from '@/components/seo/meta-fields';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { bodyToHtml } from '@/lib/article-body';
import { uploadImage } from '@/lib/upload-image';
type Category = { id: number; name: string };
type Post = {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    body: string;
    blog_category_id?: number;
    cover_image_url?: string;
    status: 'draft' | 'published';
    published_at?: string;
    featured: boolean;
    tags?: { id: number; name: string }[];
};
type SeoContext = {
    siteName: string;
    titleTemplate: string;
    baseUrl: string;
    defaultImage: string | null;
    intents: Record<string, string>;
    pageOptions: PageOption[];
    isPublished: boolean;
};
/** Mesma regra do Str::slug do servidor, só para a prévia do endereço. */
const slugify = (value: string) =>
    value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 180);
export default function BlogForm({
    categories,
    existingTags,
    post,
    seo,
    seoContext,
}: {
    categories: Category[];
    existingTags: string[];
    post: Post | null;
    seo: SeoMetaRecord | null;
    seoContext: SeoContext;
}) {
    const [uploadingCover, setUploadingCover] = useState(false);
    const [coverUploadError, setCoverUploadError] = useState<string | null>(
        null,
    );
    const [tagInput, setTagInput] = useState('');
    const form = useForm({
        title: post?.title ?? '',
        excerpt: post?.excerpt ?? '',
        body: post?.body ? bodyToHtml(post.body) : '',
        blog_category_id: post?.blog_category_id
            ? String(post.blog_category_id)
            : '',
        cover_image_url: post?.cover_image_url ?? '',
        status: post?.status ?? 'draft',
        published_at: post?.published_at?.slice(0, 16) ?? '',
        featured: post?.featured ?? false,
        tags: post?.tags?.map((tag) => tag.name) ?? ([] as string[]),
        slug: post?.slug ?? '',
        create_redirect: true,
        seo: seoMetaForm(seo),
    });
    const effectiveSlug = form.data.slug || slugify(form.data.title);
    const slugChanged =
        post !== null && form.data.slug !== '' && form.data.slug !== post.slug;
    const { analysis, loading: analyzing } = useEditorialAnalysis({
        title: form.data.title,
        meta_title: form.data.seo.meta_title,
        meta_description: form.data.seo.meta_description,
        excerpt: form.data.excerpt,
        slug: effectiveSlug,
        body: form.data.body,
        focus_keyword: form.data.seo.focus_keyword,
        post_id: post?.id ?? null,
    });
    const setSeoField = <K extends keyof SeoMetaForm>(
        field: K,
        value: SeoMetaForm[K],
    ) => form.setData('seo', { ...form.data.seo, [field]: value });
    const addTag = (raw: string) => {
        const name = raw.trim();

        if (!name) {
return;
}

        const alreadyAdded = form.data.tags.some(
            (tag) => tag.toLowerCase() === name.toLowerCase(),
        );

        if (!alreadyAdded) {
            form.setData('tags', [...form.data.tags, name]);
        }

        setTagInput('');
    };
    const removeTag = (name: string) => {
        form.setData(
            'tags',
            form.data.tags.filter((tag) => tag !== name),
        );
    };
    const onTagInputKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            addTag(tagInput);
        } else if (
            event.key === 'Backspace' &&
            !tagInput &&
            form.data.tags.length > 0
        ) {
            removeTag(form.data.tags[form.data.tags.length - 1]);
        }
    };
    const uploadCoverImage = async (file: File) => {
        setUploadingCover(true);
        setCoverUploadError(null);

        try {
            form.setData('cover_image_url', await uploadImage(file));
        } catch (error) {
            setCoverUploadError(
                error instanceof Error
                    ? error.message
                    : 'Não foi possível enviar a imagem.',
            );
        } finally {
            setUploadingCover(false);
        }
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (post) {
            form.put(`/admin/blog/posts/${post.id}`);
        } else {
            form.post('/admin/blog/posts');
        }
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
                                Endereço (slug)
                                <input
                                    className={field}
                                    value={form.data.slug}
                                    placeholder={slugify(form.data.title)}
                                    maxLength={180}
                                    onChange={(e) =>
                                        form.setData(
                                            'slug',
                                            e.target.value.toLowerCase(),
                                        )
                                    }
                                />
                                <span className="mt-1 block text-xs font-normal break-all text-muted-foreground">
                                    {seoContext.baseUrl}/blog/
                                    {effectiveSlug || '…'}
                                    {post
                                        ? ' · trocar o título não muda o endereço; só este campo muda.'
                                        : ' · vazio gera a partir do título.'}
                                </span>
                                <Err text={form.errors.slug} />
                            </label>
                            {slugChanged && seoContext.isPublished && (
                                <label className="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                                    <input
                                        type="checkbox"
                                        className="mt-0.5"
                                        checked={form.data.create_redirect}
                                        onChange={(e) =>
                                            form.setData(
                                                'create_redirect',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    <span>
                                        Criar redirecionamento permanente (301)
                                        de <code>/blog/{post?.slug}</code> para
                                        o novo endereço. Recomendado: o artigo
                                        já está publicado e pode ter links
                                        externos.
                                    </span>
                                </label>
                            )}
                            <label className="text-sm font-medium">
                                Resumo
                                <textarea
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
                            <div className="text-sm font-medium">
                                Conteúdo
                                <div className="mt-1">
                                    <RichTextEditor
                                        key={post?.id ?? 'new'}
                                        value={form.data.body}
                                        onChange={(html) =>
                                            form.setData('body', html)
                                        }
                                        onUploadImage={uploadImage}
                                        error={form.errors.body}
                                    />
                                </div>
                            </div>
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
                                        uploading={uploadingCover}
                                        preview={form.data.cover_image_url}
                                        onFile={uploadCoverImage}
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
                            <div className="text-sm font-medium">
                                Tags
                                <span className="mt-1 block text-xs font-normal text-muted-foreground">
                                    Digite o nome e aperte Enter (ou vírgula)
                                    para adicionar.
                                </span>
                                <div
                                    className={`${field} flex flex-wrap items-center gap-2`}
                                >
                                    {form.data.tags.map((tag) => (
                                        <span
                                            key={tag}
                                            className="inline-flex items-center gap-1 rounded-full bg-primary/10 py-1 pr-1.5 pl-2.5 text-xs font-semibold text-primary"
                                        >
                                            {tag}
                                            <button
                                                type="button"
                                                onClick={() => removeTag(tag)}
                                                className="rounded-full p-0.5 hover:bg-primary/20"
                                                aria-label={`Remover tag ${tag}`}
                                            >
                                                <X className="size-3" />
                                            </button>
                                        </span>
                                    ))}
                                    <input
                                        list="existing-tags"
                                        value={tagInput}
                                        onChange={(e) =>
                                            setTagInput(e.target.value)
                                        }
                                        onKeyDown={onTagInputKeyDown}
                                        onBlur={() => addTag(tagInput)}
                                        placeholder={
                                            form.data.tags.length
                                                ? ''
                                                : 'gestão, produtividade...'
                                        }
                                        className="min-w-32 flex-1 bg-transparent outline-none"
                                    />
                                    <datalist id="existing-tags">
                                        {existingTags.map((name) => (
                                            <option key={name} value={name} />
                                        ))}
                                    </datalist>
                                </div>
                                <Err text={form.errors.tags} />
                            </div>
                            {coverUploadError && (
                                <p className="rounded-md bg-destructive/10 px-3 py-2 text-sm text-destructive">
                                    {coverUploadError}
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
                            <section className="space-y-4 border-t pt-5">
                                <div>
                                    <h2 className="text-base font-semibold">
                                        SEO do artigo
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        Campos vazios herdam o título, o resumo
                                        e a capa do artigo e as configurações
                                        globais de SEO.
                                    </p>
                                </div>
                                <EditorialAnalysis
                                    analysis={analysis}
                                    loading={analyzing}
                                />
                                <SeoMetaFields
                                    data={form.data.seo}
                                    setField={setSeoField}
                                    errors={form.errors}
                                    errorPrefix="seo."
                                    defaults={{
                                        title: seoContext.titleTemplate
                                            .replace(
                                                '%title%',
                                                form.data.title || 'Título',
                                            )
                                            .replace(
                                                '%site%',
                                                seoContext.siteName,
                                            ),
                                        description: form.data.excerpt,
                                        canonical: `${seoContext.baseUrl}/blog/${effectiveSlug}`,
                                        robots: 'index, follow',
                                        ogImage:
                                            form.data.cover_image_url ||
                                            seoContext.defaultImage,
                                    }}
                                    siteName={seoContext.siteName}
                                    intents={seoContext.intents}
                                    pageOptions={seoContext.pageOptions}
                                />
                            </section>
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

        if (file) {
onFile(file);
}
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
