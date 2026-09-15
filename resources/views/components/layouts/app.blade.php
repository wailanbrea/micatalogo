<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name', 'MiCatalogo') }}</title>
        <meta name="description" content="{{ $description ?? 'Encuentra tiendas y productos locales en República Dominicana con atención directa por WhatsApp en MiCatalogo.' }}">
        <link rel="canonical" href="{{ $canonical ?? url()->current() }}">

        @php
            $shouldNoIndex = isset($robots) 
                ? ($robots === 'noindex') 
                : (request()->is('panel*', 'admin*', 'login*', 'register*', 'forgot-password*', 'reset-password*', 'email/*') || request()->has('q'));
            $metaRobots = isset($robots) ? $robots : ($shouldNoIndex ? 'noindex, follow' : 'index, follow');
            $metaImage = $ogImage ?? asset('images/logo.png');
        @endphp
        <meta name="robots" content="{{ $metaRobots }}">

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="{{ $ogType ?? 'website' }}">
        <meta property="og:site_name" content="MiCatalogo">
        <meta property="og:title" content="{{ $title ?? config('app.name', 'MiCatalogo') }}">
        <meta property="og:description" content="{{ $description ?? 'Vitrina digital y catálogos de comercio local en República Dominicana con contacto directo por WhatsApp.' }}">
        <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
        <meta property="og:image" content="{{ $metaImage }}">

        <!-- Twitter -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title ?? config('app.name', 'MiCatalogo') }}">
        <meta name="twitter:description" content="{{ $description ?? 'Vitrina digital y catálogos de comercio local en República Dominicana.' }}">
        <meta name="twitter:image" content="{{ $metaImage }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body>
        {{ $slot }}

        @livewireScripts
    </body>
</html>
