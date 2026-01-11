<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $site->name }} - Pagina's</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(12px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }
        .stagger-1 { animation-delay: 0.05s; opacity: 0; }
        .stagger-2 { animation-delay: 0.1s; opacity: 0; }
        .stagger-3 { animation-delay: 0.15s; opacity: 0; }
        .stagger-4 { animation-delay: 0.2s; opacity: 0; }
    </style>
</head>
<body class="bg-[#1a1d2e] min-h-screen text-slate-100 font-sans antialiased">

    <x-sidebar />

    <main class="ml-20 p-10" x-data="{
        period: '{{ $period }}',
        periods: {
            '7d': '7d',
            '30d': '30d',
            '90d': '90d',
            'all-time': 'All-time'
        },
        contentFilter: 'all',
        searchQuery: '',
        changePeriod(newPeriod) {
            window.location.href = '?period=' + newPeriod;
        }
    }">
        <div class="max-w-[1800px] mx-auto">

            <!-- Header -->
            <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <a href="{{ route('pages.index') }}" class="text-slate-400 hover:text-slate-300 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>
                        <h1 class="text-4xl font-light text-slate-100">{{ $site->name }}</h1>
                    </div>
                    <div class="flex items-center gap-3 ml-8">
                        <p class="text-slate-400">Content performance insights</p>
                        <span class="text-slate-600">•</span>
                        <div class="flex items-center gap-2">
                            <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></div>
                            <p class="text-xs text-slate-500">Updated {{ now()->diffForHumans() }}</p>
                        </div>
                    </div>
                </div>

                <!-- Period Selector -->
                <div class="flex gap-2">
                    <template x-for="(label, key) in periods" :key="key">
                        <button
                            @click="changePeriod(key)"
                            :class="period === key ? 'bg-lavender text-white' : 'bg-[#252839] text-slate-400 hover:bg-[#2d3048]'"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200"
                            x-text="label">
                        </button>
                    </template>
                </div>
            </div>

            <!-- Hero Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="animate-fade-in-up stagger-1">
                    <div class="bg-[#252839] backdrop-blur-light rounded-xl p-6 shadow-lg border border-slate-700/20">
                        <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Active Pages</p>
                        <p class="text-3xl font-light text-white">{{ number_format($summaryMetrics['total_pages']) }}</p>
                    </div>
                </div>
                <div class="animate-fade-in-up stagger-1">
                    <div class="bg-[#252839] backdrop-blur-light rounded-xl p-6 shadow-lg border border-slate-700/20">
                        <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Total Pageviews</p>
                        <p class="text-3xl font-light text-white">{{ number_format($summaryMetrics['total_pageviews']) }}</p>
                    </div>
                </div>
                <div class="animate-fade-in-up stagger-2">
                    <div class="bg-[#252839] backdrop-blur-light rounded-xl p-6 shadow-lg border border-slate-700/20">
                        <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Total Clicks</p>
                        <p class="text-3xl font-light text-white">{{ number_format($summaryMetrics['total_clicks']) }}</p>
                    </div>
                </div>
                <div class="animate-fade-in-up stagger-2">
                    <div class="bg-[#252839] backdrop-blur-light rounded-xl p-6 shadow-lg border border-slate-700/20">
                        <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Average CTR</p>
                        <p class="text-3xl font-light text-white">{{ number_format($summaryMetrics['average_ctr'], 1) }}%</p>
                    </div>
                </div>
            </div>

            <!-- Top 10 Products -->
            <div class="mb-8 animate-fade-in-up stagger-3">
                <div class="bg-[#252839] backdrop-blur-light rounded-xl p-8 shadow-lg border border-slate-700/20">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-1 h-5 bg-emerald-500 rounded-full"></div>
                        <h3 class="text-xl font-medium text-slate-100">Top 10 Producten</h3>
                        <span class="text-sm text-slate-500">(meeste pageviews)</span>
                    </div>

                    @if($topProducts->isNotEmpty())
                        <div class="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0">
                            <table class="w-full min-w-[600px]">
                                <thead>
                                    <tr class="border-b border-slate-700/30">
                                        <th class="text-left text-xs text-slate-400 font-medium pb-3 pr-4">#</th>
                                        <th class="text-left text-xs text-slate-400 font-medium pb-3 pr-4">Pagina</th>
                                        <th class="text-right text-xs text-slate-400 font-medium pb-3 pr-4">Pageviews</th>
                                        <th class="text-right text-xs text-slate-400 font-medium pb-3 pr-4">Clicks</th>
                                        <th class="text-right text-xs text-slate-400 font-medium pb-3">CTR</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($topProducts->values() as $index => $page)
                                        <tr class="border-b border-slate-700/20 hover:bg-base-800/30 transition-colors {{ $index < 3 ? 'bg-gradient-to-r from-emerald-500/5 to-transparent' : '' }}">
                                            <td class="py-3 pr-4">
                                                <span class="text-sm {{ $index < 3 ? 'text-emerald-400 font-semibold' : 'text-slate-400' }}">{{ $index + 1 }}</span>
                                            </td>
                                            <td class="py-3 pr-4">
                                                <a href="https://{{ $site->domain }}{{ $page->pathname }}" target="_blank"
                                                   class="text-sm {{ $index < 3 ? 'text-slate-100 font-medium' : 'text-slate-200' }} hover:text-lavender transition-colors line-clamp-1 block">
                                                    {{ $page->title ?? $page->pathname }}
                                                </a>
                                                <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">{{ $page->pathname }}</p>
                                            </td>
                                            <td class="py-3 pr-4 text-right text-sm {{ $index < 3 ? 'text-white font-medium' : 'text-slate-200' }}">{{ number_format($page->total_pageviews) }}</td>
                                            <td class="py-3 pr-4 text-right text-sm text-emerald-400 font-medium">{{ number_format($page->total_clicks) }}</td>
                                            <td class="py-3 text-right text-sm text-slate-400">
                                                {{ number_format($page->ctr ?? 0, 1) }}%
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-slate-500 text-sm">Geen product pagina's gevonden</p>
                    @endif
                </div>
            </div>

            <!-- Top 10 Blogs & Reviews -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

                <!-- Top 10 Blogs -->
                <div class="animate-fade-in-up stagger-4">
                    <div class="bg-[#252839] backdrop-blur-light rounded-xl p-8 shadow-lg border border-slate-700/20">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-1 h-5 bg-sky-500 rounded-full"></div>
                            <h3 class="text-xl font-medium text-slate-100">Top 10 Blogs</h3>
                        </div>

                        @if($topBlogs->isNotEmpty())
                            <div class="space-y-2">
                                @foreach($topBlogs->values() as $index => $page)
                                    <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-base-800/30 transition-colors">
                                        <span class="text-sm font-medium text-slate-500 w-6">{{ $index + 1 }}</span>
                                        <div class="flex-1 min-w-0">
                                            <a href="https://{{ $site->domain }}{{ $page->pathname }}" target="_blank"
                                               class="text-sm text-slate-200 hover:text-lavender transition-colors line-clamp-1 block">
                                                {{ $page->title ?? $page->pathname }}
                                            </a>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-xs text-slate-500">{{ number_format($page->total_pageviews) }} views</span>
                                                <span class="text-slate-600">•</span>
                                                <span class="text-xs text-slate-500">{{ number_format($page->total_clicks) }} clicks</span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-sky-400">{{ number_format($page->ctr ?? 0, 1) }}%</div>
                                            <div class="text-xs text-slate-500">CTR</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-slate-500 text-sm">Geen blog pagina's gevonden</p>
                        @endif
                    </div>
                </div>

                <!-- Top 10 Reviews -->
                <div class="animate-fade-in-up stagger-4">
                    <div class="bg-[#252839] backdrop-blur-light rounded-xl p-8 shadow-lg border border-slate-700/20">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-1 h-5 bg-amber-500 rounded-full"></div>
                            <h3 class="text-xl font-medium text-slate-100">Top 10 Reviews</h3>
                        </div>

                        @if($topReviews->isNotEmpty())
                            <div class="space-y-2">
                                @foreach($topReviews->values() as $index => $page)
                                    <div class="flex items-center gap-3 p-3 rounded-lg hover:bg-base-800/30 transition-colors">
                                        <span class="text-sm font-medium text-slate-500 w-6">{{ $index + 1 }}</span>
                                        <div class="flex-1 min-w-0">
                                            <a href="https://{{ $site->domain }}{{ $page->pathname }}" target="_blank"
                                               class="text-sm text-slate-200 hover:text-lavender transition-colors line-clamp-1 block">
                                                {{ $page->title ?? $page->pathname }}
                                            </a>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-xs text-slate-500">{{ number_format($page->total_pageviews) }} views</span>
                                                <span class="text-slate-600">•</span>
                                                <span class="text-xs text-slate-500">{{ number_format($page->total_clicks) }} clicks</span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-amber-400">{{ number_format($page->ctr ?? 0, 1) }}%</div>
                                            <div class="text-xs text-slate-500">CTR</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-slate-500 text-sm">Geen review pagina's gevonden</p>
                        @endif
                    </div>
                </div>

            </div>

            <!-- All Pages Table -->
            <div class="animate-fade-in-up stagger-4">
                <div class="bg-[#252839] backdrop-blur-light rounded-xl p-8 shadow-lg border border-slate-700/20">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                        <div class="flex items-center gap-3">
                            <div class="w-1 h-5 bg-lavender rounded-full"></div>
                            <h3 class="text-lg font-medium text-slate-100">Alle Pagina's</h3>
                        </div>

                        <!-- Filters -->
                        <div class="flex flex-col sm:flex-row gap-3">
                            <!-- Search -->
                            <input
                                type="text"
                                x-model="searchQuery"
                                placeholder="Zoek pagina..."
                                class="px-4 py-2 bg-[#1a1d2e] text-slate-200 border border-slate-700/50 rounded-lg text-sm focus:outline-none focus:border-lavender/50 transition-colors w-full sm:w-64">

                            <!-- Content Type Filter -->
                            <div class="flex gap-2 bg-[#1a1d2e]/50 backdrop-blur-light rounded-lg p-1">
                                <button @click="contentFilter = 'all'"
                                        :class="contentFilter === 'all' ? 'bg-lavender text-white' : 'text-slate-400 hover:text-slate-300'"
                                        class="px-3 py-1.5 rounded text-xs font-medium transition-all duration-200">
                                    All
                                </button>
                                <button @click="contentFilter = 'product'"
                                        :class="contentFilter === 'product' ? 'bg-lavender text-white' : 'text-slate-400 hover:text-slate-300'"
                                        class="px-3 py-1.5 rounded text-xs font-medium transition-all duration-200">
                                    Product
                                </button>
                                <button @click="contentFilter = 'blog'"
                                        :class="contentFilter === 'blog' ? 'bg-lavender text-white' : 'text-slate-400 hover:text-slate-300'"
                                        class="px-3 py-1.5 rounded text-xs font-medium transition-all duration-200">
                                    Blog
                                </button>
                                <button @click="contentFilter = 'review'"
                                        :class="contentFilter === 'review' ? 'bg-lavender text-white' : 'text-slate-400 hover:text-slate-300'"
                                        class="px-3 py-1.5 rounded text-xs font-medium transition-all duration-200">
                                    Review
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0">
                        <table class="w-full min-w-[700px]">
                            <thead>
                                <tr class="border-b border-slate-700/30">
                                    <th class="text-left text-xs text-slate-400 font-medium pb-3 pr-4">Pagina</th>
                                    <th class="text-left text-xs text-slate-400 font-medium pb-3 pr-4">Type</th>
                                    <th class="text-right text-xs text-slate-400 font-medium pb-3 pr-4">Pageviews</th>
                                    <th class="text-right text-xs text-slate-400 font-medium pb-3 pr-4">Clicks</th>
                                    <th class="text-right text-xs text-slate-400 font-medium pb-3">CTR</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $allPages = $topPages->toArray();
                                @endphp
                                <template x-for="(page, index) in {{ json_encode($allPages) }}.filter(p =>
                                    (contentFilter === 'all' || p.content_type === contentFilter) &&
                                    (searchQuery === '' ||
                                     (p.title && p.title.toLowerCase().includes(searchQuery.toLowerCase())) ||
                                     (p.pathname && p.pathname.toLowerCase().includes(searchQuery.toLowerCase())))
                                )" :key="index">
                                    <tr class="border-b border-slate-700/20 hover:bg-base-800/30 transition-colors">
                                        <td class="py-4 pr-4">
                                            <a :href="'https://{{ $site->domain }}' + page.pathname" target="_blank"
                                               class="text-sm text-slate-200 hover:text-lavender transition-colors line-clamp-1 block">
                                                <span x-text="page.title || page.pathname"></span>
                                            </a>
                                            <p class="text-xs text-slate-500 mt-0.5" x-text="page.pathname"></p>
                                        </td>
                                        <td class="py-4 pr-4">
                                            <span x-show="page.content_type"
                                                  class="inline-block px-2 py-1 bg-base-800/50 text-slate-400 text-xs rounded capitalize"
                                                  x-text="page.content_type"></span>
                                            <span x-show="!page.content_type" class="text-xs text-slate-600">-</span>
                                        </td>
                                        <td class="py-4 pr-4 text-right text-sm text-slate-200" x-text="Number(page.total_pageviews).toLocaleString()"></td>
                                        <td class="py-4 pr-4 text-right text-sm text-emerald-400" x-text="Number(page.total_clicks).toLocaleString()"></td>
                                        <td class="py-4 text-right text-sm text-slate-400" x-text="Number(page.ctr || 0).toFixed(1) + '%'"></td>
                                    </tr>
                                </template>
                                <tr x-show="{{ json_encode($allPages) }}.filter(p =>
                                    (contentFilter === 'all' || p.content_type === contentFilter) &&
                                    (searchQuery === '' ||
                                     (p.title && p.title.toLowerCase().includes(searchQuery.toLowerCase())) ||
                                     (p.pathname && p.pathname.toLowerCase().includes(searchQuery.toLowerCase())))
                                ).length === 0">
                                    <td colspan="5" class="py-12 text-center text-slate-500 text-sm">
                                        Geen pagina's gevonden
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

</body>
</html>
