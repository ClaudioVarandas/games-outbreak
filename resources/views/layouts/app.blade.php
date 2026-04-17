<!DOCTYPE html>
<html lang="en" class="dark">
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

@stack('scripts')

</body>
</html>
