{{-- Artigo no HTML inicial. O React substitui esta marcação ao carregar (ver App\Services\Prerender). --}}
@php
    // Artigos antigos usam a sintaxe simples do editor anterior (ver resources/js/lib/article-body.ts).
    $isHtml = preg_match('#</?(p|h2|h3|ul|ol|li|figure|img|strong|em|a|br|blockquote)[ >]#i', (string) $post['body']) === 1;
    $body = \App\Support\HtmlSanitizer::clean($isHtml ? (string) $post['body'] : \Illuminate\Support\Str::markdown((string) $post['body'], ['html_input' => 'escape', 'allow_unsafe_links' => false]));
@endphp
<main>
    <article>
        <header class="border-b border-slate-200 bg-gradient-to-b from-blue-50 to-white py-14 sm:py-20">
            <div class="mx-auto max-w-4xl px-5 sm:px-8">
                <a href="/blog" class="text-sm font-bold text-brand-blue">Voltar ao blog</a>
                @if (! empty($post['category']))
                    <p class="mt-10 text-sm"><a href="/blog?category={{ $post['category']['slug'] }}" class="rounded-full bg-blue-100 px-3 py-1 font-bold text-brand-blue-hover">{{ $post['category']['name'] }}</a></p>
                @endif
                <h1 class="mt-6 text-4xl leading-[1.08] font-bold tracking-[-0.045em] text-balance text-slate-950 sm:text-6xl">{{ $post['title'] }}</h1>
                <p class="mt-6 text-xl leading-8 text-slate-600">{{ $post['excerpt'] }}</p>
                @if (! empty($post['author']))
                    <p class="mt-7 text-sm text-slate-600">Por <strong class="text-slate-900">{{ $post['author']['name'] }}</strong></p>
                @endif
            </div>
        </header>
        <div class="mx-auto max-w-4xl px-5 sm:px-8">
            <div class="article-content">{!! $body !!}</div>
        </div>
    </article>
    @if (count($relatedPosts) > 0)
        <section class="mx-auto mt-16 max-w-4xl px-5 sm:px-8">
            <h2 class="text-2xl font-bold text-slate-950">Continue lendo</h2>
            <ul class="mt-5 grid gap-2">
                @foreach ($relatedPosts as $related)
                    <li><a href="/blog/{{ $related['slug'] }}" class="font-bold text-brand-blue">{{ $related['title'] }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
</main>
