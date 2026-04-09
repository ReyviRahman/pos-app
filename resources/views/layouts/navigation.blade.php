<!-- We do not need a new x-data here since it's inherited from body's sidebarOpen -->
<div>
    <!-- Mobile overlay -->
    <div x-show="sidebarOpen" x-cloak class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-sm lg:hidden"
        @click="sidebarOpen = false" x-transition:enter="transition-opacity ease-linear duration-300"
        x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"></div>

    <!-- Sidebar Container -->
    <nav :class="{'translate-x-0 lg:ml-0': sidebarOpen, '-translate-x-full lg:-ml-72': !sidebarOpen}"
        class="fixed inset-y-0 left-0 z-50 w-72 bg-white shadow-2xl lg:shadow-none lg:border-r border-slate-100 transform transition-all duration-300 ease-in-out lg:static lg:translate-x-0 lg:h-screen lg:flex lg:flex-col overflow-hidden flex-shrink-0">

        <!-- Header / Logo -->
        <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-white flex-shrink-0">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <div
                    class="bg-indigo-600 text-white rounded-xl w-9 h-9 flex items-center justify-center font-bold text-lg shadow-sm">
                    R
                </div>
                <div class="flex flex-col">
                    <span class="font-bold text-xl tracking-tight text-slate-900">SDR <span
                            class="text-indigo-600">POS</span></span>
                    <span class="text-xs text-slate-500 font-medium">
                        {{ auth()->user()->branch->name ?? 'Belum ada cabang' }}
                    </span>
                </div>
            </a>
            <button @click="sidebarOpen = false"
                class="lg:hidden p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                    </path>
                </svg>
            </button>
        </div>

        <!-- Links -->
        <div class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            <div class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest mb-3 px-3">Main</div>

            <a href="{{ route('dashboard') }}"
                class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-50' }}">
                <svg class="w-5 h-5 {{ request()->routeIs('dashboard') ? 'text-indigo-600' : 'text-slate-400' }}"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                    </path>
                </svg>
                Dashboard
            </a>

            @if(auth()->user()->role === 'waiter' || auth()->user()->role === 'kasir')
                <div
                    class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest mt-8 mb-3 px-3 flex justify-between items-center">
                    <span>Point of Sales</span>
                </div>
            @endif

            @if(auth()->user()->role === 'waiter')
                <a href="{{ route('order') }}"
                    class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('order') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-50' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 {{ request()->routeIs('order') ? 'text-indigo-600' : 'text-slate-400' }}"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                        Order
                    </div>
                </a>
            @endif

            @if(auth()->user()->role === 'chef')
                <a href="{{ route('kitchen.index') }}"
                    class="flex items-center justify-between px-3 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('kitchen.*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-50' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 {{ request()->routeIs('kitchen.*') ? 'text-indigo-600' : 'text-slate-400' }}"
                            xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48">
                            <g fill="none">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4"
                                    d="M14 30v10c0 6 8 6 8 0V30m0 7h-8" />
                                <path stroke="currentColor" stroke-width="4"
                                    d="M14 6a2 2 0 0 1 2-2h16.635c.319 0 .632.075.888.265C34.542 5.025 37.198 7.582 38 14c.773 6.182-1.369 12.364-2.382 14.855c-.288.71-.985 1.145-1.75 1.145H14z" />
                                <circle cx="22" cy="10" r="2" fill="currentColor" />
                            </g>
                        </svg>
                        Order
                    </div>
                </a>
            @endif

            @if(auth()->user()->role === 'kasir')
                <a href="{{ route('payment') }}"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('payment') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-50' }}">
                    <svg class="w-5 h-5 {{ request()->routeIs('payment') ? 'text-indigo-600' : 'text-slate-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                        </path>
                    </svg>
                    Pembayaran
                </a>
            @endif

            @if(in_array(auth()->user()->role, ['kasir', 'admin', 'manajer']))
                <div class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest mt-8 mb-3 px-3">Laporan &
                    Riwayat</div>
                <a href="{{ route('history.index') }}"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('history.*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-50' }}">
                    <svg class="w-5 h-5 {{ request()->routeIs('history.*') ? 'text-indigo-600' : 'text-slate-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                        </path>
                    </svg>
                    Riwayat Transaksi
                </a>
            @endif

            @if(in_array(auth()->user()->role, ['admin', 'manajer']))
                <div class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest mt-8 mb-3 px-3">Manajemen
                    Resto</div>

                <a href="{{ route('product.index') }}"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('product.*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-50' }}">
                    <svg class="w-5 h-5 {{ request()->routeIs('product.*') ? 'text-indigo-600' : 'text-slate-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z">
                        </path>
                    </svg>
                    Daftar Menu
                </a>

                <a href="{{ route('bahan.index') }}"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('bahan.*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-50' }}">
                    <svg class="w-5 h-5 {{ request()->routeIs('bahan.*') ? 'text-indigo-600' : 'text-slate-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    Stok Bahan Baku
                </a>

                <a href="{{ route('inventory-movement.index') }}"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl font-medium transition {{ request()->routeIs('inventory-movement.*') ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-500 hover:text-indigo-600 hover:bg-slate-50' }}">
                    <svg class="w-5 h-5 {{ request()->routeIs('inventory-movement.*') ? 'text-indigo-600' : 'text-slate-400' }}"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                    </svg>
                    Mutasi Stok
                </a>
            @endif
        </div>

        <!-- User Profile & Logout -->
        <div class="p-4 border-t border-slate-100 bg-white flex-shrink-0">
            <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl mb-3">
                <div
                    class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 font-bold text-lg flex-shrink-0">
                    {{ strtoupper(substr(Auth::user()->name ?? Auth::user()->username ?? 'U', 0, 1)) }}
                </div>
                <div class="flex-1 overflow-hidden">
                    <div class="font-bold text-slate-800 text-sm truncate">
                        {{ Auth::user()->name ?? Auth::user()->username }}
                    </div>
                    <div class="text-xs text-slate-500 truncate">{{ Auth::user()->email }}</div>
                </div>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('profile.edit') }}"
                    class="flex-1 flex items-center justify-center gap-2 px-3 py-2.5 text-sm font-semibold text-slate-600 bg-white rounded-xl border border-slate-200 hover:bg-slate-50 hover:text-indigo-600 transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z">
                        </path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="flex-1">
                    @csrf
                    <button type="submit"
                        class="w-full h-full flex items-center justify-center gap-2 px-3 py-2.5 text-sm font-semibold text-rose-600 bg-rose-50 rounded-xl hover:bg-rose-100 transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1">
                            </path>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </nav>
</div>