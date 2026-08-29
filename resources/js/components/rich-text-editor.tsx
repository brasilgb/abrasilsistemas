import ImageExtension from '@tiptap/extension-image';
import LinkExtension from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Heading2,
    Heading3,
    ImagePlus,
    Italic,
    Link as LinkIcon,
    List,
    LoaderCircle,
    Redo2,
    Undo2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Toggle } from '@/components/ui/toggle';

export function RichTextEditor({
    value,
    onChange,
    onUploadImage,
    placeholder,
    error,
}: {
    value: string;
    onChange: (html: string) => void;
    onUploadImage: (file: File) => Promise<string>;
    placeholder?: string;
    error?: string;
}) {
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const editor = useEditor({
        extensions: [
            StarterKit.configure({ heading: { levels: [2, 3] } }),
            ImageExtension,
            LinkExtension.configure({ openOnClick: false, autolink: true }),
            Placeholder.configure({
                placeholder:
                    placeholder ?? 'Comece o artigo com uma introdução...',
            }),
        ],
        content: value,
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
        editorProps: {
            attributes: {
                class: 'article-content min-h-64 w-full rounded-b-md border border-t-0 bg-background px-3 py-2 text-sm outline-none',
            },
        },
    });

    const insertImage = async (file: File) => {
        if (!editor) {
            return;
        }

        setUploading(true);
        setUploadError(null);

        try {
            const url = await onUploadImage(file);
            editor.chain().focus().setImage({ src: url }).run();
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

    const addLink = () => {
        if (!editor) {
            return;
        }

        const previous = editor.getAttributes('link').href as
            string | undefined;
        const url = window.prompt('Endereço do link:', previous ?? 'https://');

        if (url === null) {
            return;
        }

        if (url === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();

            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url })
            .run();
    };

    if (!editor) {
        return null;
    }

    return (
        <div>
            <div className="flex flex-wrap items-center gap-1 rounded-t-md border border-b-0 bg-muted/30 p-1">
                <Toggle
                    size="sm"
                    pressed={editor.isActive('bold')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleBold().run()
                    }
                    aria-label="Negrito"
                >
                    <Bold />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('italic')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleItalic().run()
                    }
                    aria-label="Itálico"
                >
                    <Italic />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('heading', { level: 2 })}
                    onPressedChange={() =>
                        editor.chain().focus().toggleHeading({ level: 2 }).run()
                    }
                    aria-label="Subtítulo"
                >
                    <Heading2 />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('heading', { level: 3 })}
                    onPressedChange={() =>
                        editor.chain().focus().toggleHeading({ level: 3 }).run()
                    }
                    aria-label="Seção"
                >
                    <Heading3 />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('bulletList')}
                    onPressedChange={() =>
                        editor.chain().focus().toggleBulletList().run()
                    }
                    aria-label="Lista"
                >
                    <List />
                </Toggle>
                <Toggle
                    size="sm"
                    pressed={editor.isActive('link')}
                    onPressedChange={addLink}
                    aria-label="Link"
                >
                    <LinkIcon />
                </Toggle>
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="hidden"
                    onChange={(event) => {
                        const file = event.target.files?.[0];

                        if (file) {
                            insertImage(file);
                        }

                        event.target.value = '';
                    }}
                />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    disabled={uploading}
                    onClick={() => fileInputRef.current?.click()}
                    aria-label="Inserir imagem"
                >
                    {uploading ? (
                        <LoaderCircle className="animate-spin" />
                    ) : (
                        <ImagePlus />
                    )}
                </Button>
                <div className="mx-1 h-5 w-px bg-border" />
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    disabled={!editor.can().undo()}
                    onClick={() => editor.chain().focus().undo().run()}
                    aria-label="Desfazer"
                >
                    <Undo2 />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    disabled={!editor.can().redo()}
                    onClick={() => editor.chain().focus().redo().run()}
                    aria-label="Refazer"
                >
                    <Redo2 />
                </Button>
            </div>
            <EditorContent editor={editor} />
            {uploadError && (
                <p className="mt-1 text-xs text-destructive">{uploadError}</p>
            )}
            {error && <p className="mt-1 text-xs text-destructive">{error}</p>}
        </div>
    );
}
