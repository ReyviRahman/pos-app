<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>POS Sains De Resto</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
            background-size: 20px 20px;
        }
    </style>
</head>

<body class="antialiased min-h-screen flex items-center justify-center p-6 text-slate-800">

    <div class="max-w-4xl w-full mx-auto flex flex-col items-center text-center">

        <!-- Logo / Icon -->
        <div
            class="mb-8 p-4 bg-white rounded-3xl shadow-xl shadow-slate-200 ring-1 ring-slate-100 flex items-center justify-center">
            <svg class="w-16 h-16 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                </path>
            </svg>
        </div>

        <!-- Title -->
        <h1 class="text-5xl md:text-6xl font-extrabold tracking-tight text-slate-900 mb-6 uppercase">
            POS Sains De Resto
        </h1>

        <!-- Subtitle -->
        <p class="text-xl text-slate-500 mb-12 max-w-2xl font-medium">
            Internal Point of Sale System for efficient order management and seamless transactions.
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-col sm:flex-row gap-4 mb-16 w-full justify-center">
            @if (Route::has('login'))
                @auth
                    <a href="{{ url('/order') }}      "
                        class="bg-indigo-600 text-white px-10 py-5 rounded-full font-bold text-lg hover:bg-indigo-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex items-center justify-center gap-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7">
                            </path>
                        </svg>
                        Masuk Terminal Kasir
                    </a>
                    <a href="{{ url('/dashboard') }}"
                        class="bg-white text-slate-700 border border-slate-200 px-8 py-5 rounded-full font-bold text-lg hover:bg-slate-50 hover:border-slate-300 transition-all duration-300">
                        Manajemen Backoffice
                    </a>
                @else
                    <a href="{{ route('login') }}"
                        class="bg-indigo-600 text-white px-12 py-5 rounded-full font-bold text-xl hover:bg-indigo-700 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex items-center justify-center gap-3 w-full sm:w-auto">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1">
                            </path>
                        </svg>
                        Log in
                    </a>
                @endauth
            @endif
        </div>

        <!-- Optional Dashboard Image (Kept minimal just to show interface) -->
        <div
            class="relative w-full max-w-3xl mx-auto rounded-3xl shadow-2xl overflow-hidden border-4 border-white transform rotate-1 hover:rotate-0 transition-transform duration-500 opacity-90 hover:opacity-100">
            <img src="{{ asset('images/hero.png') }}" alt="Resto POS Interface" class="w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/40 to-transparent"></div>
        </div>

    </div>

</body>

</html>