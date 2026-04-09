<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SDR POS') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="font-sans antialiased text-slate-800 bg-gray-50 h-screen overflow-hidden">
    <div class="flex h-screen w-full overflow-hidden" x-data="{ sidebarOpen: window.innerWidth >= 1024 }">
        @include('layouts.navigation')

        <div class="flex-1 flex flex-col h-screen overflow-hidden relative min-w-0">
            <header
                class="bg-white shadow-sm z-10 flex items-center justify-between p-4 border-b border-slate-100 bg-white/80 backdrop-blur-md">

                <div class="flex items-center gap-3">
                    <button @click="sidebarOpen = !sidebarOpen"
                        class="p-2 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <div class="flex lg:hidden items-center gap-2">
                        <div
                            class="bg-indigo-600 text-white rounded-md w-7 h-7 flex items-center justify-center font-bold text-xs shadow-sm">
                            R
                        </div>
                        <div class="font-bold text-base text-slate-800 tracking-tight">SDR POS</div>
                    </div>
                </div>

                <div class="flex items-center gap-4">

                    <div
                        class="hidden md:flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 text-slate-700 rounded-md border border-slate-200">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.242-4.243a8 8 0 1111.314 0z">
                            </path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="text-xs font-semibold tracking-wide">
                            {{ auth()->user()->branch->name ?? 'Cabang Tidak Diketahui' }}
                        </span>
                    </div>

                    <div class="hidden md:block w-px h-6 bg-slate-200"></div>

                    <div class="flex items-center gap-3">
                        <div class="hidden sm:flex flex-col text-right">
                            <span class="text-sm font-semibold text-slate-700">
                                {{ auth()->user()->name ?? 'Guest User' }}
                            </span>
                            <span class="text-xs text-slate-500 capitalize">
                                {{ auth()->user()->role ?? '' }}
                            </span>
                        </div>

                        <div
                            class="w-9 h-9 rounded-full bg-indigo-100 text-indigo-700 border border-indigo-200 flex items-center justify-center font-bold text-sm shadow-sm">
                            {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                        </div>
                    </div>
                </div>

            </header>

            @isset($header)
                <header class="bg-white z-10 hidden sm:block border-b border-slate-100/50">
                    <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="flex-1 overflow-y-auto w-full">
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
</body>

</html>