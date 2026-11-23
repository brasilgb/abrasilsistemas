{{-- Listagem do blog no HTML inicial. O React substitui esta marcação ao carregar (ver App\Services\Prerender). --}}
<section class="border-b border-slate-200 bg-gradient-to-b from-blue-50 to-white py-16 sm:py-24">
    <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-12">
        <h1 class="max-w-3xl text-5xl font-bold tracking-[-0.05em] text-balance text-slate-950 sm:text-7xl">Tecnologia aplicada a negócios reais.</h1>
        <p class="mt-6 max-w-3xl text-lg leading-8 text-slate-600 sm:text-xl">Dicas, experiências e ideias sobre gestão, sistemas, sites e tecnologia para ajudar sua empresa a trabalhar melhor.</p>
    </div>
</section>
<main class="mx-auto max-w-7xl px-5 py-16 sm:px-8 lg:px-12">
    @foreach ($posts['data'] as $post)
        <article class="border-t border-slate-200 py-6">
            <h2 class="text-xl font-bold text-slate-950"><a href="/blog/{{ $post['slug'] }}">{{ $post['title'] }}</a></h2>
            <p class="mt-2 text-slate-600">{{ $post['excerpt'] }}</p>
        </article>
    @endforeach
    @if ($posts['prev_page_url'] || $posts['next_page_url'])
        <nav aria-label="Paginação" class="mt-8 flex gap-4">
            @if ($posts['prev_page_url'])
                <a href="{{ $posts['prev_page_url'] }}">Página anterior</a>
            @endif
            @if ($posts['next_page_url'])
                <a href="{{ $posts['next_page_url'] }}">Próxima página</a>
            @endif
        </nav>
    @endif
</main>
