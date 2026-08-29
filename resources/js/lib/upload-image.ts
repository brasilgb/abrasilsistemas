/** Upload an image file to the admin blog image endpoint and return its public URL. */
export async function uploadImage(
    file: File,
    endpoint = '/admin/blog/images',
): Promise<string> {
    if (!file.type.startsWith('image/')) {
        throw new Error('Selecione uma imagem JPG, PNG ou WebP.');
    }

    const data = new FormData();
    data.append('image', file);

    const csrfToken = document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content');

    const response = await fetch(endpoint, {
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

    return result.url;
}
