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

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @php $seo = $page['props']['seo'] ?? []; @endphp
        <x-inertia::head>
            <title>{{ $seo['title'] ?? config('app.name', 'Laravel') }}</title>
            @if (! empty($seo['description']))
                <meta name="description" content="{{ $seo['description'] }}">
            @endif
            @if (! empty($seo['robots']))
                <meta name="robots" content="{{ $seo['robots'] }}">
            @endif
            @if (! empty($seo['canonical']))
                <link rel="canonical" href="{{ $seo['canonical'] }}">
            @endif
            @if (! empty($seo['siteName']))
                <meta property="og:site_name" content="{{ $seo['siteName'] }}">
            @endif
            @if (! empty($seo['locale']))
                <meta property="og:locale" content="{{ $seo['locale'] }}">
            @endif
            @if (! empty($seo))
                <meta property="og:type" content="{{ $seo['ogType'] ?? 'website' }}">
                <meta property="og:title" content="{{ $seo['ogTitle'] ?? $seo['title'] ?? config('app.name') }}">
                @if (! empty($seo['ogDescription'] ?? $seo['description'] ?? null))
                    <meta property="og:description" content="{{ $seo['ogDescription'] ?? $seo['description'] }}">
                @endif
                @if (! empty($seo['ogImage']))
                    <meta property="og:image" content="{{ $seo['ogImage'] }}">
                    <meta name="twitter:card" content="summary_large_image">
                    <meta name="twitter:image" content="{{ $seo['ogImage'] }}">
                @endif
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
