<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => $appearance === 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{--
            Brand assets live in public/images/brand and are served straight off disk,
            so they are reachable from here as well as from React. `asset()` resolves
            them against APP_URL, which is what makes og:image absolute — a relative
            one is dropped by every crawler.
        --}}
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/brand/favicon-32.png') }}">
        <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">

        {{--
            A fallback title, because there is no SSR: the `title` callback in app.tsx
            runs on the client, so without this the tab is blank until React mounts and
            a crawler that does not execute JS sees no title at all. Inertia's head
            manager overwrites it as soon as a page declares its own.

            The tag carries no `data-inertia` attribute on purpose: the head manager
            drops `title:not([data-inertia])` the moment a page supplies a title, which
            is exactly the handover we want. Marking it would opt it into management and
            defeat that.

            og:title carries the app name for the same reason, except where a route hands
            over a `sharePreview` through withViewData — the booking page, so a shared
            link shows the business instead of the app.
        --}}
        @isset($sharePreview)
            @php
                $shareTitle = $sharePreview->city === null
                    ? __('share.business_page.title', ['name' => $sharePreview->name])
                    : __('share.business_page.title_with_city', ['name' => $sharePreview->name, 'city' => $sharePreview->city]);
                $shareDescription = filled($sharePreview->about)
                    ? Str::limit(Str::squish($sharePreview->about), 160)
                    : __('share.business_page.description', ['name' => $sharePreview->name]);
            @endphp
        @endisset
        <title>{{ $shareTitle ?? config('app.name') }}</title>
        @isset($shareDescription)
            <meta name="description" content="{{ $shareDescription }}">
        @endisset
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $shareTitle ?? config('app.name') }}">
        @isset($shareDescription)
            <meta property="og:description" content="{{ $shareDescription }}">
        @endisset
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        @isset($sharePreview->imageUrl)
            <meta property="og:image" content="{{ $sharePreview->imageUrl }}">
        @else
            <meta property="og:image" content="{{ asset('images/brand/og-image.png') }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
        @endisset
        <meta name="twitter:card" content="summary_large_image">
        @isset($shareTitle)
            <meta name="twitter:title" content="{{ $shareTitle }}">
            <meta name="twitter:description" content="{{ $shareDescription }}">
        @endisset

        {{-- Inline and in <head> on purpose: it has to run before the first paint, so it cannot
             move into app.tsx or any bundled file. Only `system` reaches it — an explicit choice
             is already on the html tag above. --}}
        <script>
            if ('{{ $appearance }}' === 'system'
                && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        </script>

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-background text-foreground antialiased">
        @inertia
    </body>
</html>
