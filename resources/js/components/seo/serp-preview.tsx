/**
 * Prévia aproximada do resultado de busca e do card de compartilhamento. O buscador
 * pode reescrever título e descrição: é uma referência, não uma garantia.
 */
export function SerpPreview({
    title,
    description,
    url,
    siteName,
}: {
    title: string;
    description: string;
    url: string;
    siteName: string;
}) {
    const display = url.replace(/^https?:\/\//, '').replace(/\/$/, '');
    const [host, ...path] = display.split('/');

    return (
        <div className="rounded-lg border bg-white p-4 text-left dark:bg-zinc-950">
            <div className="flex items-center gap-2">
                <span className="flex size-7 items-center justify-center rounded-full bg-zinc-100 text-[10px] font-bold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    {siteName.slice(0, 2).toUpperCase()}
                </span>
                <span className="min-w-0 leading-tight">
                    <span className="block truncate text-sm text-zinc-900 dark:text-zinc-100">
                        {siteName}
                    </span>
                    <span className="block truncate text-xs text-zinc-600 dark:text-zinc-400">
                        {host}
                        {path.length > 0 && ` › ${path.join(' › ')}`}
                    </span>
                </span>
            </div>
            <p className="mt-2 line-clamp-1 text-lg leading-snug text-[#1a0dab] dark:text-[#8ab4f8]">
                {truncate(title, 60) || 'Título da página'}
            </p>
            <p className="mt-1 line-clamp-2 text-sm text-zinc-700 dark:text-zinc-300">
                {truncate(description, 160) ||
                    'Sem descrição: o buscador escolherá um trecho da página.'}
            </p>
            <p className="mt-3 text-[11px] text-muted-foreground">
                Prévia aproximada. O Google pode exibir outro título ou trecho.
            </p>
        </div>
    );
}

export function SocialPreview({
    title,
    description,
    image,
    url,
}: {
    title: string;
    description: string;
    image?: string | null;
    url: string;
}) {
    return (
        <div className="overflow-hidden rounded-lg border bg-card">
            {image ? (
                <img
                    src={image}
                    alt=""
                    className="aspect-[1200/630] w-full bg-muted object-cover"
                />
            ) : (
                <div className="flex aspect-[1200/630] items-center justify-center bg-muted text-xs text-muted-foreground">
                    Sem imagem social
                </div>
            )}
            <div className="space-y-0.5 p-3">
                <p className="truncate text-[11px] text-muted-foreground uppercase">
                    {url.replace(/^https?:\/\//, '').split('/')[0]}
                </p>
                <p className="line-clamp-1 text-sm font-semibold">{title}</p>
                <p className="line-clamp-2 text-xs text-muted-foreground">
                    {description}
                </p>
            </div>
        </div>
    );
}

function truncate(value: string, max: number): string {
    return value.length > max ? `${value.slice(0, max - 1).trimEnd()}…` : value;
}
