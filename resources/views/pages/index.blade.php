<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagina's - Site Selectie</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-[#1a1d2e] min-h-screen text-slate-100 font-sans antialiased">

    <x-sidebar />

    <main class="ml-20 p-10">
        <div class="max-w-[1400px] mx-auto">

            <div class="mb-8">
                <h1 class="text-4xl font-light text-slate-100 mb-2">Pagina Performance</h1>
                <p class="text-slate-400">Selecteer een site om de best presterende pagina's te bekijken</p>
            </div>

            <!-- Sites Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @forelse($sites as $site)
                    <a href="{{ route('pages.show', $site->id) }}"
                       class="group bg-[#252839] backdrop-blur-light rounded-xl p-6 shadow-lg border border-slate-700/20 hover:bg-[#2d3048] transition-all duration-300 hover:-translate-y-1">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <h3 class="font-medium text-slate-100 mb-1 group-hover:text-lavender transition-colors">
                                    {{ $site->name }}
                                </h3>
                                <p class="text-xs text-slate-500">{{ $site->domain }}</p>
                            </div>
                            <svg class="w-5 h-5 text-slate-600 group-hover:text-lavender transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                        @if($site->niche)
                            <span class="inline-block px-2 py-1 bg-[#1a1d2e]/50 text-slate-400 text-xs rounded">
                                {{ $site->niche }}
                            </span>
                        @endif
                    </a>
                @empty
                    <div class="col-span-full text-center py-12">
                        <p class="text-slate-500">Geen sites gevonden</p>
                    </div>
                @endforelse
            </div>

        </div>
    </main>

</body>
</html>
