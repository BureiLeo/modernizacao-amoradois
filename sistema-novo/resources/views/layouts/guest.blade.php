<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#953C36">

        <title>{{ config('app.name', 'Amor a Dois') }}</title>

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" href="{{ asset('images/brand/favicon-32x32.png') }}" sizes="32x32">
        <link rel="apple-touch-icon" href="{{ asset('images/brand/apple-touch-icon.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-brand-text antialiased">
        <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10 bg-brand-background">
            <div class="mb-6">
                <img
                    src="{{ asset('images/brand/logo-amor-a-dois.png') }}"
                    alt="Amor a Dois Personalizados"
                    class="h-28 w-auto object-contain"
                >
            </div>

            <div class="w-full sm:max-w-md px-6 py-7 bg-brand-surface shadow-brand-lg rounded-brand-lg border border-brand-border/30">
                {{ $slot }}
            </div>

            <p class="mt-6 text-xs text-brand-text-muted">Sistema de gestão interno &middot; Amor a Dois Personalizados</p>
        </div>
    </body>
</html>

