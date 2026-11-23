import BlogPublicLayout from '@/components/blog-public-layout';

/** Cabeçalho e rodapé do blog; o conteúdo vem do banco (resources/views/prerender/*.blade.php). */
export default function BlogShell() {
    return <BlogPublicLayout>__AB_CONTENT__</BlogPublicLayout>;
}
