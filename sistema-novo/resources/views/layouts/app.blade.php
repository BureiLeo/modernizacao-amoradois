<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#953C36">
        <meta name="description" content="Amor a Dois Personalizados — sistema interno de gestão.">

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
    <body class="font-sans antialiased bg-brand-background text-brand-text">
        <livewire:layout.navigation />

        <div class="lg:pl-64 lg:pt-20 pb-20 lg:pb-0 min-h-screen">
            @if (isset($header))
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-5">
                    {{ $header }}
                </div>
            @endif

            <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>

