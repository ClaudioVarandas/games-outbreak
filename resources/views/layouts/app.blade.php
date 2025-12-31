{{-- resources/views/layouts/app.blade.php --}}
    <!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - Games Outbreak</title>
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
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex flex-col">

<x-header />

<main class="flex-1">
    @yield('content')
</main>

<x-footer />

@stack('scripts')

</body>
</html>
