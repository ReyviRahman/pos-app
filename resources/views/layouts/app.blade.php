<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SDR POS') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="font-sans antialiased text-slate-800 bg-gray-50 h-screen overflow-hidden">
    <div class="flex h-screen w-full overflow-hidden" x-data="{ sidebarOpen: window.innerWidth >= 1024 }">
        <!-- Sidebar -->
        @include('layouts.navigation')

        <!-- Main Content -->
        <div class="flex-1 flex flex-col h-screen overflow-hidden relative min-w-0">
            <!-- Top App Bar (Visible on all screens) -->
            <header class="bg-white shadow-sm z-10 flex items-center justify-between p-4 border-b border-slate-100 bg-white/80 backdrop-blur-md">
                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = !sidebarOpen" class="p-2 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <!-- Short Branding on mobile when sidebar opens -->
                    <div class="flex lg:hidden items-center gap-2">
                        <div class="bg-indigo-600 text-white rounded-md w-7 h-7 flex items-center justify-center font-bold text-xs shadow-sm">
                            R
                        </div>
                        <div class="font-bold text-base text-slate-800 tracking-tight">SDR POS</div>
                    </div>
                </div>
            </header>

            <!-- Page Heading (Only visible if $header is explicitly passed and usually hidden on POS pages) -->
            @isset($header)
                <header class="bg-white z-10 hidden sm:block border-b border-slate-100/50">
                    <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Content -->
            <main class="flex-1 overflow-y-auto w-full">
                {{ $slot }}
            </main>
        </div>
    </div>
    
    @livewireScripts
</body>

</html>