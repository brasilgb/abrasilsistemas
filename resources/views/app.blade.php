<!DOCTYPE html>
<html lang="pt-BR" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="shortcut icon" href="/images/favicon/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="16x16" href="/images/favicon/favicon-16x16.png">
        <link rel="icon" type="image/png" sizes="32x32" href="/images/favicon/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="192x192" href="/images/favicon/android-chrome-192x192.png">
        <link rel="icon" type="image/png" sizes="512x512" href="/images/favicon/android-chrome-512x512.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/images/favicon/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700&display=swap" rel="stylesheet">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @php $seo = $page['props']['seo'] ?? []; @endphp
        {{--
            As tags abaixo saem no HTML inicial (o que buscadores e prévias de links leem).
            O data-inertia de cada uma é a mesma chave (head-key) usada pelo componente
            SeoHead no React: depois de carregar, o Inertia substitui cada tag pela sua
            versão do cliente em vez de duplicá-la.
        --}}
        <x-inertia::head>
            <title>{{ $seo['title'] ?? config('app.name', 'Laravel') }}</title>
            @if (! empty($seo['description']))
                <meta name="description" content="{{ $seo['description'] }}" data-inertia="description">
            @endif
            @if (! empty($seo['robots']))
                <meta name="robots" content="{{ $seo['robots'] }}" data-inertia="robots">
            @endif
            @if (! empty($seo['canonical']))
                <link rel="canonical" href="{{ $seo['canonical'] }}" data-inertia="canonical">
            @endif
            @if (! empty($seo['siteName']))
                <meta property="og:site_name" content="{{ $seo['siteName'] }}" data-inertia="og:site_name">
            @endif
            @if (! empty($seo['locale']))
                <meta property="og:locale" content="{{ $seo['locale'] }}" data-inertia="og:locale">
            @endif
            @if (! empty($seo))
                <meta property="og:type" content="{{ $seo['ogType'] ?? 'website' }}" data-inertia="og:type">
                <meta property="og:title" content="{{ $seo['ogTitle'] ?? $seo['title'] ?? config('app.name') }}" data-inertia="og:title">
                @if (! empty($seo['ogDescription'] ?? $seo['description'] ?? null))
                    <meta property="og:description" content="{{ $seo['ogDescription'] ?? $seo['description'] }}" data-inertia="og:description">
                @endif
                @if (! empty($seo['canonical']))
                    <meta property="og:url" content="{{ $seo['canonical'] }}" data-inertia="og:url">
                @endif
                @if (! empty($seo['ogImage']))
                    <meta property="og:image" content="{{ $seo['ogImage'] }}" data-inertia="og:image">
                    @if (! empty($seo['ogImageAlt']))
                        <meta property="og:image:alt" content="{{ $seo['ogImageAlt'] }}" data-inertia="og:image:alt">
                    @endif
                    <meta name="twitter:card" content="summary_large_image" data-inertia="twitter:card">
                    <meta name="twitter:image" content="{{ $seo['ogImage'] }}" data-inertia="twitter:image">
                @endif
            @endif
            @if (! empty($seo['jsonLd']))
                <script type="application/ld+json" data-inertia="json-ld">{!! json_encode($seo['jsonLd'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
