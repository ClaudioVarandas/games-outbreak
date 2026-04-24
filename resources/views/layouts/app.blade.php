<!DOCTYPE html>
<html lang="@yield('html-lang', str_replace('_', '-', app()->getLocale()))" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('android-chrome-192x192.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('android-chrome-512x512.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <title>@yield('title') | Games Outbreak</title>
    @hasSection('meta-description')
        <meta name="description" content="@yield('meta-description')">
    @endif
    @stack('seo')
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-PXQKLNW241"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-PXQKLNW241');
    </script>
    <!-- End Google tag (gtag.js) -->
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        .carousel-inner {
            scroll-behavior: smooth;
        }
        [x-cloak] {
            display: none !important;
        }
    </style>
</head>
<body class="@yield('body-class', 'bg-gray-100 dark:bg-gray-900') text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">

<x-header />

<main class="flex-1">
    @yield('content')
</main>

<x-footer />

@guest
    <x-auth.login-modal />
    <x-auth.register-modal />
    <x-auth.forgot-password-modal />
@endguest

<div id="go-video-lightbox" class="fixed inset-0 z-[100000] hidden items-center justify-center bg-[rgba(5,5,10,0.82)] backdrop-blur-md opacity-0 transition-opacity duration-200" aria-modal="true" role="dialog" aria-label="{{ __('Play video') }}" aria-hidden="true">
    <div class="relative w-[min(1100px,92vw)] aspect-video bg-black rounded-[14px] overflow-hidden shadow-[0_40px_120px_rgba(0,0,0,.6)]">
        <button type="button" id="go-video-lightbox-close" class="absolute -top-10 right-0 bg-transparent border-0 text-white font-mono text-xs uppercase tracking-[0.12em] cursor-pointer opacity-80 hover:opacity-100" aria-label="{{ __('Close video') }}">
            {{ __('Close') }} &#10005;
        </button>
        <iframe id="go-video-lightbox-frame" class="h-full w-full border-0 block" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen title="{{ __('Video player') }}"></iframe>
    </div>
</div>

@stack('scripts')

</body>
</html>
