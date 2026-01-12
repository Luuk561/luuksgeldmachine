<!-- Mobile Menu Toggle (alleen zichtbaar op mobiel) -->
<div class="lg:hidden fixed top-0 left-0 right-0 h-16 bg-[#1a1d2e]/95 backdrop-blur-md z-50 flex items-center justify-between px-4 border-b border-slate-700/30">
    <h1 class="text-lg font-light text-slate-100">luuksgeldmachine</h1>
    <button @click="mobileMenuOpen = !mobileMenuOpen" class="w-10 h-10 rounded-lg bg-lavender/15 flex items-center justify-center hover:bg-lavender/25 transition-colors">
        <svg class="w-6 h-6 text-lavender" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
</div>

<!-- Sidebar: Desktop (altijd zichtbaar) + Mobile (slideable) -->
<aside
    x-data="{ mobileMenuOpen: false }"
    :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    class="fixed left-0 top-0 h-full w-20 bg-base-900/95 backdrop-blur-light z-40 flex flex-col transition-transform duration-300 ease-in-out lg:z-50">

    <!-- Overlay voor mobiel (sluit menu bij klik) -->
    <div x-show="mobileMenuOpen" @click="mobileMenuOpen = false" class="lg:hidden fixed inset-0 bg-black/50 -z-10"></div>

    <div class="flex-1 flex flex-col items-center py-8 space-y-6 mt-16 lg:mt-0">

        <!-- Home -->
        <a href="{{ route('dashboard') }}" class="group flex flex-col items-center gap-1" title="Home">
            <div class="w-10 h-10 rounded-lg bg-lavender/15 flex items-center justify-center hover:bg-lavender/25 transition-colors">
                <svg class="w-5 h-5 text-lavender" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
            </div>
        </a>

        <!-- Sites -->
        <a href="{{ route('sites.index') }}" class="group flex flex-col items-center gap-1" title="Sites">
            <div class="w-10 h-10 rounded-lg bg-slate-700/20 flex items-center justify-center hover:bg-slate-700/40 transition-colors">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/>
                </svg>
            </div>
        </a>

        <!-- Pagina's -->
        <a href="{{ route('pages.index') }}" class="group flex flex-col items-center gap-1" title="Pagina's">
            <div class="w-10 h-10 rounded-lg bg-slate-700/20 flex items-center justify-center hover:bg-slate-700/40 transition-colors">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
        </a>

        <!-- Content -->
        <a href="#" class="group flex flex-col items-center gap-1" title="Content">
            <div class="w-10 h-10 rounded-lg bg-slate-700/20 flex items-center justify-center hover:bg-slate-700/40 transition-colors">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </div>
        </a>

        <!-- Producten -->
        <a href="#" class="group flex flex-col items-center gap-1" title="Producten">
            <div class="w-10 h-10 rounded-lg bg-slate-700/20 flex items-center justify-center hover:bg-slate-700/40 transition-colors">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
        </a>

        <!-- Rapportage -->
        <a href="#" class="group flex flex-col items-center gap-1" title="Rapportage">
            <div class="w-10 h-10 rounded-lg bg-slate-700/20 flex items-center justify-center hover:bg-slate-700/40 transition-colors">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
        </a>

        <!-- Onderhoud -->
        <a href="#" class="group flex flex-col items-center gap-1" title="Onderhoud">
            <div class="w-10 h-10 rounded-lg bg-slate-700/20 flex items-center justify-center hover:bg-slate-700/40 transition-colors">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
        </a>

    </div>

    <!-- Bottom items -->
    <div class="flex flex-col items-center pb-8 space-y-6 border-t border-slate-700/30 pt-6">

        <!-- Instellingen -->
        <a href="#" class="group flex flex-col items-center gap-1" title="Instellingen">
            <div class="w-10 h-10 rounded-lg bg-slate-700/20 flex items-center justify-center hover:bg-slate-700/40 transition-colors">
                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                </svg>
            </div>
        </a>

        <!-- Logout -->
        <form method="POST" action="{{ route('logout') }}" class="w-full flex justify-center">
            @csrf
            <button type="submit" class="group flex flex-col items-center gap-1" title="Uitloggen">
                <div class="w-10 h-10 rounded-lg bg-red-500/20 flex items-center justify-center hover:bg-red-500/30 transition-colors">
                    <svg class="w-5 h-5 text-red-400 group-hover:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </div>
            </button>
        </form>

    </div>
</aside>
