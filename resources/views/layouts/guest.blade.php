<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Web Learning</title>

        @include('layouts.partials.pwa-head')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#F7F5F0] font-sans text-[#171717] antialiased">
        <div class="flex min-h-screen items-center justify-center px-5 py-12 sm:px-6">
            <main class="w-full">
                <div class="w-full max-w-[420px] mx-auto">
                    <a href="{{ url('/') }}" class="mb-10 block text-center text-xl font-semibold tracking-[-0.02em] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-4 focus-visible:ring-offset-[#F7F5F0]" aria-label="Web Learning ホーム">
                        <span class="text-[#3155D9]">web</span> <span class="text-[#171717]">learning</span><span class="text-[#3155D9]">.</span>
                    </a>

                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
