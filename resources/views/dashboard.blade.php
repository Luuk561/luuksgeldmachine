<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Affiliate Control</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-[#1a1d2e] min-h-screen text-slate-100 font-sans antialiased">

    <x-sidebar />

    <!-- Main Canvas: Responsive padding en margin -->
    <main class="lg:ml-20 ml-0 p-4 sm:p-6 lg:p-10 pt-20 lg:pt-10" x-data="{
        period: new URLSearchParams(window.location.search).get('period') || '30d',
        chartMetric: new URLSearchParams(window.location.search).get('chartMetric') || 'commission',
        showManualModal: false,
        statuses: (() => {
            const params = new URLSearchParams(window.location.search);
            const statusParams = params.getAll('statuses[]');
            return statusParams.length > 0 ? statusParams : ['Geaccepteerd', 'Open'];
        })(),
        periods: {
            '1d': { label: '1d' },
            '7d': { label: '7d' },
            '30d': { label: '30d' },
            '90d': { label: '90d' },
            '365d': { label: '1y' },
            'all-time': { label: 'All' }
        },
        metrics: {
            'commission': { label: 'Commissie', color: 'rgb(139, 92, 246)', prefix: '€' },
            'visitors': { label: 'Visitors', color: 'rgb(34, 197, 94)', prefix: '' },
            'pageviews': { label: 'Pageviews', color: 'rgb(59, 130, 246)', prefix: '' },
            'clicks': { label: 'Affiliate Clicks', color: 'rgb(249, 115, 22)', prefix: '' }
        },
        changePeriod(newPeriod) {
            const params = new URLSearchParams(window.location.search);
            params.set('period', newPeriod);
            window.location.href = '/?' + params.toString();
        },
        changeChartMetric(metric) {
            const params = new URLSearchParams(window.location.search);
            params.set('chartMetric', metric);
            window.location.href = '/?' + params.toString();
        },
        applyFilters() {
            const params = new URLSearchParams(window.location.search);
            params.delete('statuses[]');
            this.statuses.forEach(status => params.append('statuses[]', status));
            window.location.href = '/?' + params.toString();
        }
    }">

        <!-- Header with Period Selector on Right (mobile: stacked) -->
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-6 sm:mb-8">
            <div class="hidden lg:block">
                <h1 class="text-3xl sm:text-4xl font-light text-slate-100 mb-2">luuksgeldmachine</h1>
                <p class="text-sm sm:text-base text-slate-400">Waar moet je nu op focussen om meer te verdienen?</p>

                <!-- Data Freshness Indicator -->
                @if($dataFreshness['last_sync'])
                    <div class="mt-2 flex items-center gap-2 text-xs">
                        @php
                            $minutesAgo = $dataFreshness['minutes_ago'];
                            $status = $dataFreshness['last_log']->status ?? 'unknown';

                            if ($status === 'success' && $minutesAgo <= 20) {
                                $color = 'text-green-400';
                                $icon = '●';
                                $text = "Gesynchroniseerd {$minutesAgo} min geleden";
                            } elseif ($status === 'success' && $minutesAgo <= 60) {
                                $color = 'text-yellow-400';
                                $icon = '●';
                                $text = "Gesynchroniseerd {$minutesAgo} min geleden";
                            } else {
                                $color = 'text-red-400';
                                $icon = '●';
                                $text = $status === 'failed' ? 'Laatste sync gefaald' : "Niet gesynchroniseerd ({$minutesAgo} min)";
                            }
                        @endphp
                        <span class="{{ $color }}">{{ $icon }}</span>
                        <span class="text-slate-500">{{ $text }}</span>
                    </div>
                @else
                    <div class="mt-2 flex items-center gap-2 text-xs">
                        <span class="text-red-400">●</span>
                        <span class="text-slate-500">Nog niet gesynchroniseerd</span>
                    </div>
                @endif
            </div>

            <!-- Period Selector - Desktop: Right Top, Mobile: Horizontal scroll -->
            <div class="flex gap-2 overflow-x-auto pb-2 lg:pb-0 scrollbar-hide">
                <template x-for="(periodData, periodKey) in periods" :key="periodKey">
                    <button
                        @click="changePeriod(periodKey)"
                        :class="period === periodKey ? 'bg-lavender text-white shadow-lg shadow-lavender/30' : 'bg-[#252839] text-slate-400 hover:bg-[#2d3048]'"
                        class="px-3 sm:px-4 py-2 rounded-lg transition-all duration-200 text-xs sm:text-sm font-medium whitespace-nowrap flex-shrink-0"
                        x-text="periodData.label"
                    ></button>
                </template>
            </div>
        </div>

        @foreach(['1d', '7d', '30d', '90d', '365d', 'all-time'] as $periodKey)
        <div x-show="period === '{{ $periodKey }}'" x-transition x-cloak>
            @php
                $metrics = $metricsData[$periodKey] ?? null;
                // For 1d, use hourly data; for others, use daily data
                $chartData = $periodKey === '1d'
                    ? ($hourlyMetricsData[$periodKey] ?? collect())
                    : ($dailyMetricsData[$periodKey] ?? collect());
                $dailyData = $dailyMetricsData[$periodKey] ?? collect();
            @endphp

            @if($metrics)
            <!-- Main Grid: Chart Left (Larger), Metrics Right -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

                <!-- LEFT: Chart - Takes 2 columns -->
                <div class="lg:col-span-2 bg-[#252839] backdrop-blur-light rounded-xl shadow-lg border border-slate-700/20 flex flex-col">
                    <!-- Chart Metric Switcher: Horizontal scroll on mobile -->
                    <div class="flex gap-2 p-4 sm:p-6 pb-3 sm:pb-4 overflow-x-auto scrollbar-hide">
                        <template x-for="(metricData, metricKey) in metrics" :key="metricKey">
                            <button
                                @click="chartMetric = metricKey; window['updateChart{{ str_replace('-', '', $periodKey) }}'](metricKey)"
                                :class="chartMetric === metricKey ? 'bg-lavender text-white shadow-md' : 'bg-[#1a1d2e] text-slate-400 hover:bg-[#2d3048]'"
                                class="px-2 sm:px-3 py-1 sm:py-1.5 rounded-lg transition-all duration-200 text-xs font-medium whitespace-nowrap flex-shrink-0"
                                x-text="metricData.label"
                            ></button>
                        </template>
                    </div>
                    <div class="flex-1 px-4 sm:px-6 pb-4 sm:pb-6">
                        <canvas id="chart-{{ $periodKey }}"></canvas>
                    </div>
                </div>

                <!-- RIGHT: Metrics - Takes 1 column -->
                <div class="flex flex-col gap-4">
                    <!-- Commission Card: Responsive padding and font sizes -->
                    <div class="bg-gradient-to-br from-[#2d3048] to-[#252839] backdrop-blur-light rounded-xl p-4 sm:p-6 shadow-lg border border-slate-700/20 flex-1">
                        <div class="flex items-start justify-between mb-2">
                            <p class="text-xs uppercase tracking-wider text-slate-400">COMMISSIE</p>
                            <button @click="showManualModal = true"
                                    class="text-lavender hover:text-lavender/80 transition-colors"
                                    title="Handmatige commissie toevoegen">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </button>
                        </div>
                        <p class="text-4xl sm:text-5xl font-light text-white mb-4">€{{ number_format($metrics->commission, 2, '.', '') }}</p>

                        <!-- Order Status Checkboxes -->
                        <div class="mb-4">
                            <p class="text-xs uppercase tracking-wider text-slate-400 mb-3">ORDER STATUS</p>
                            <div class="flex flex-col gap-2">
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox"
                                           x-model="statuses"
                                           value="Geaccepteerd"
                                           class="w-4 h-4 rounded bg-[#1a1d2e] border-slate-600 text-lavender focus:ring-lavender focus:ring-offset-0 transition-colors">
                                    <span class="text-sm text-slate-300 group-hover:text-slate-200 transition-colors">Geaccepteerd</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox"
                                           x-model="statuses"
                                           value="Open"
                                           class="w-4 h-4 rounded bg-[#1a1d2e] border-slate-600 text-lavender focus:ring-lavender focus:ring-offset-0 transition-colors">
                                    <span class="text-sm text-slate-300 group-hover:text-slate-200 transition-colors">In behandeling</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer group">
                                    <input type="checkbox"
                                           x-model="statuses"
                                           value="Geweigerd"
                                           class="w-4 h-4 rounded bg-[#1a1d2e] border-slate-600 text-lavender focus:ring-lavender focus:ring-offset-0 transition-colors">
                                    <span class="text-sm text-slate-300 group-hover:text-slate-200 transition-colors">Afgekeurd</span>
                                </label>
                            </div>
                        </div>

                        <button @click="applyFilters()"
                                class="w-full bg-lavender hover:bg-lavender/90 text-white py-2.5 rounded-lg transition-all duration-200 text-sm font-medium shadow-lg shadow-lavender/30">
                            Toepassen
                        </button>
                    </div>

                    <!-- Orders & Visitors Row: Responsive padding -->
                    <div class="grid grid-cols-2 gap-3 sm:gap-4">
                        <div class="bg-[#252839] backdrop-blur-light rounded-xl p-3 sm:p-4 shadow-lg border border-slate-700/20">
                            <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">Orders</p>
                            <p class="text-2xl sm:text-3xl font-light text-white">{{ number_format($metrics->orders) }}</p>
                        </div>
                        <div class="bg-[#252839] backdrop-blur-light rounded-xl p-3 sm:p-4 shadow-lg border border-slate-700/20">
                            <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">Visitors</p>
                            <p class="text-2xl sm:text-3xl font-light text-white">{{ number_format($metrics->visitors) }}</p>
                        </div>
                    </div>

                    <!-- RPV & Average per Day/Hour Row -->
                    @php
                        $isHourly = $periodKey === '1d';
                        $divisor = match($periodKey) {
                            '1d' => now()->hour + 1, // Hours passed today (1-24)
                            '7d' => 7,
                            '30d' => 30,
                            '90d' => 90,
                            '365d' => 365,
                            'all-time' => $dailyData->count(),
                            default => 30
                        };
                        $avgValue = $divisor > 0 ? $metrics->commission / $divisor : 0;
                        $avgLabel = $isHourly ? 'Gem. per Uur' : 'Gem. per Dag';
                    @endphp
                    <div class="grid grid-cols-2 gap-3 sm:gap-4">
                        <div class="bg-[#252839] backdrop-blur-light rounded-xl p-3 sm:p-4 shadow-lg border border-slate-700/20">
                            <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">RPV</p>
                            <p class="text-2xl sm:text-3xl font-light text-white">€{{ number_format($metrics->rpv, 3, '.', '') }}</p>
                        </div>
                        <div class="bg-[#252839] backdrop-blur-light rounded-xl p-3 sm:p-4 shadow-lg border border-slate-700/20">
                            <p class="text-xs uppercase tracking-wider text-slate-400 mb-1">{{ $avgLabel }}</p>
                            <p class="text-2xl sm:text-3xl font-light text-white">€{{ number_format($avgValue, 2, '.', '') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Metrics Row: Mobile 2 cols, Desktop 4 cols -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 lg:gap-6 mb-6 sm:mb-8">
                <div class="bg-[#252839] backdrop-blur-light rounded-xl p-4 sm:p-6 shadow-lg border border-slate-700/20">
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Pageviews</p>
                    <p class="text-2xl sm:text-3xl font-light text-white">{{ number_format($metrics->pageviews) }}</p>
                </div>
                <div class="bg-[#252839] backdrop-blur-light rounded-xl p-4 sm:p-6 shadow-lg border border-slate-700/20">
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Aff. Clicks</p>
                    <p class="text-2xl sm:text-3xl font-light text-white">{{ number_format($metrics->clicks) }}</p>
                </div>
                <div class="bg-[#252839] backdrop-blur-light rounded-xl p-4 sm:p-6 shadow-lg border border-slate-700/20">
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">CTR</p>
                    <p class="text-2xl sm:text-3xl font-light text-white">{{ $metrics->pageviews > 0 ? number_format(($metrics->clicks / $metrics->pageviews) * 100, 1) : 0 }}%</p>
                </div>
                <div class="bg-[#252839] backdrop-blur-light rounded-xl p-4 sm:p-6 shadow-lg border border-slate-700/20">
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Conversie</p>
                    <p class="text-2xl sm:text-3xl font-light text-white">{{ number_format($metrics->conversion_rate, 1) }}%</p>
                </div>
            </div>

            <!-- Bottom: Top Performers & Worst Sites -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Top Performers: Responsive padding -->
                <div class="bg-[#252839] backdrop-blur-light rounded-xl p-4 sm:p-6 shadow-lg border border-slate-700/20">
                    <div class="flex items-center gap-3 mb-4 sm:mb-6">
                        <div class="w-1 h-6 bg-emerald-500 rounded-full"></div>
                        <h2 class="text-lg sm:text-xl font-light text-slate-100">Top Performers</h2>
                    </div>
                    <div class="space-y-2">
                        @forelse($topSitesData[$periodKey] ?? [] as $index => $site)
                        <div class="py-3 border-b border-slate-700/20 hover:bg-[#1a1d2e]/50 transition-colors px-3 rounded">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-start gap-3 flex-1">
                                    <span class="text-xs font-semibold text-slate-500 w-4 mt-1">{{ $index + 1 }}</span>
                                    <div class="flex-1">
                                        <p class="text-slate-200 font-medium">{{ $site->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $site->domain }}</p>
                                    </div>
                                </div>
                                <p class="text-emerald-400 font-semibold text-lg">€{{ number_format($site->commission, 2, ',', '.') }}</p>
                            </div>
                            <div class="flex gap-4 ml-7 text-xs">
                                <span class="text-slate-400">
                                    <span class="text-slate-500">Orders:</span>
                                    <span class="text-slate-300 font-medium">{{ $site->orders }}</span>
                                </span>
                                <span class="text-slate-400">
                                    <span class="text-slate-500">RPV:</span>
                                    <span class="text-slate-300 font-medium">€{{ number_format($site->rpv, 3, ',', '.') }}</span>
                                </span>
                                <span class="text-slate-400">
                                    <span class="text-slate-500">Bezoekers:</span>
                                    <span class="text-slate-300 font-medium">{{ number_format($site->visitors) }}</span>
                                </span>
                            </div>
                        </div>
                        @empty
                        <p class="text-slate-500 text-center py-8">Geen data</p>
                        @endforelse
                    </div>
                </div>

                <!-- Lowest RPV (Optimization Opportunities): Responsive -->
                <div class="bg-[#252839] backdrop-blur-light rounded-xl p-4 sm:p-6 shadow-lg border border-slate-700/20">
                    <div class="flex items-center gap-3 mb-4 sm:mb-6">
                        <div class="w-1 h-6 bg-amber-500 rounded-full"></div>
                        <h2 class="text-lg sm:text-xl font-light text-slate-100">Optimization Opportunities</h2>
                    </div>
                    <div class="space-y-2">
                        @forelse($worstSitesData[$periodKey] ?? [] as $index => $site)
                        <div class="py-3 border-b border-slate-700/20 hover:bg-[#1a1d2e]/50 transition-colors px-3 rounded">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-start gap-3 flex-1">
                                    <span class="text-xs font-semibold text-slate-500 w-4 mt-1">{{ $index + 1 }}</span>
                                    <div class="flex-1">
                                        <p class="text-slate-200 font-medium">{{ $site->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $site->domain }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-amber-400 font-semibold text-lg">€{{ number_format($site->rpv, 3, ',', '.') }}</p>
                                    <p class="text-xs text-slate-500">RPV</p>
                                </div>
                            </div>
                            <div class="flex gap-4 ml-7 text-xs">
                                <span class="text-slate-400">
                                    <span class="text-slate-500">Bezoekers:</span>
                                    <span class="text-slate-300 font-medium">{{ number_format($site->visitors) }}</span>
                                </span>
                                <span class="text-slate-400">
                                    <span class="text-slate-500">Commissie:</span>
                                    <span class="text-slate-300 font-medium">€{{ number_format($site->commission, 2, ',', '.') }}</span>
                                </span>
                                @if($site->visitors > 100 && $site->commission == 0)
                                <span class="text-amber-400 font-medium">
                                    ⚡ Hoog potentieel
                                </span>
                                @endif
                            </div>
                        </div>
                        @empty
                        <p class="text-slate-500 text-center py-8">Geen data</p>
                        @endforelse
                    </div>
                </div>
            </div>
            @else
            <div class="bg-[#252839] backdrop-blur-light rounded-xl p-12 text-center border border-slate-700/20">
                <p class="text-slate-400 text-lg">Geen data beschikbaar voor deze periode</p>
                <p class="text-slate-500 text-sm mt-2">Run <code class="bg-[#1a1d2e] px-2 py-1 rounded">php artisan metrics:aggregate</code> om data te genereren</p>
            </div>
            @endif
        </div>
        @endforeach

        <!-- Manual Commission Modal -->
        <div x-show="showManualModal"
             x-cloak
             @click.self="showManualModal = false"
             class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center z-50 p-4"
             x-transition>
            <div @click.stop
                 class="bg-[#252839] rounded-xl p-8 max-w-md w-full shadow-2xl border border-slate-700/30"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100">

                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-light text-slate-100">Handmatige Commissie</h2>
                    <button @click="showManualModal = false"
                            class="text-slate-400 hover:text-slate-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('manual-commission.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-sm text-slate-400 mb-2">Site</label>
                        <select name="site_id" required
                                class="w-full bg-[#1a1d2e] border border-slate-700 rounded-lg px-4 py-2.5 text-slate-200 focus:border-lavender focus:ring-2 focus:ring-lavender/30 transition-colors">
                            <option value="">Selecteer een site...</option>
                            @foreach($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-slate-400 mb-2">Commissie (€)</label>
                            <input type="number" step="0.01" name="commission" required
                                   class="w-full bg-[#1a1d2e] border border-slate-700 rounded-lg px-4 py-2.5 text-slate-200 focus:border-lavender focus:ring-2 focus:ring-lavender/30 transition-colors"
                                   placeholder="0.00">
                        </div>

                        <div>
                            <label class="block text-sm text-slate-400 mb-2">Datum</label>
                            <input type="date" name="date" required
                                   value="{{ date('Y-m-d') }}"
                                   class="w-full bg-[#1a1d2e] border border-slate-700 rounded-lg px-4 py-2.5 text-slate-200 focus:border-lavender focus:ring-2 focus:ring-lavender/30 transition-colors">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-slate-400 mb-2">Platform</label>
                            <input type="text" name="platform" required
                                   class="w-full bg-[#1a1d2e] border border-slate-700 rounded-lg px-4 py-2.5 text-slate-200 focus:border-lavender focus:ring-2 focus:ring-lavender/30 transition-colors"
                                   placeholder="bijv. Amazon">
                        </div>

                        <div>
                            <label class="block text-sm text-slate-400 mb-2">Status</label>
                            <select name="status" required
                                    class="w-full bg-[#1a1d2e] border border-slate-700 rounded-lg px-4 py-2.5 text-slate-200 focus:border-lavender focus:ring-2 focus:ring-lavender/30 transition-colors">
                                <option value="Geaccepteerd">Geaccepteerd</option>
                                <option value="Open" selected>Open</option>
                                <option value="Geweigerd">Geweigerd</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm text-slate-400 mb-2">Notitie (optioneel)</label>
                        <textarea name="note" rows="3"
                                  class="w-full bg-[#1a1d2e] border border-slate-700 rounded-lg px-4 py-2.5 text-slate-200 focus:border-lavender focus:ring-2 focus:ring-lavender/30 transition-colors resize-none"
                                  placeholder="Eventuele extra informatie..."></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button"
                                @click="showManualModal = false"
                                class="flex-1 bg-[#1a1d2e] hover:bg-[#252839] text-slate-300 py-2.5 rounded-lg transition-colors text-sm font-medium">
                            Annuleren
                        </button>
                        <button type="submit"
                                class="flex-1 bg-lavender hover:bg-lavender/90 text-white py-2.5 rounded-lg transition-all duration-200 text-sm font-medium shadow-lg shadow-lavender/30">
                            Toevoegen
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </main>

    @php
        $hourlyData1d = $hourlyMetricsData['1d'] ?? collect();
        $hourlyDates = $hourlyData1d->map(function($m) {
            return str_pad($m->hour ?? 0, 2, '0', STR_PAD_LEFT) . ':00';
        });
    @endphp
    <script>
        // Get initial chart metric from URL
        const urlParams = new URLSearchParams(window.location.search);
        const initialChartMetric = urlParams.get('chartMetric') || 'commission';

        // Store chart instances
        const charts = {};
        const chartData = {
            // 1d uses hourly data with hour labels
            '1d': {
                dates: @json($hourlyDates),
                commission: @json($hourlyData1d->pluck('commission')),
                visitors: @json($hourlyData1d->pluck('visitors')),
                pageviews: @json($hourlyData1d->pluck('pageviews')),
                clicks: @json($hourlyData1d->pluck('clicks'))
            },
            @foreach(['7d', '30d', '90d', '365d', 'all-time'] as $periodKey)
            '{{ $periodKey }}': {
                dates: @json($dailyMetricsData[$periodKey]->pluck('date') ?? []),
                commission: @json($dailyMetricsData[$periodKey]->pluck('commission') ?? []),
                visitors: @json($dailyMetricsData[$periodKey]->pluck('visitors') ?? []),
                pageviews: @json($dailyMetricsData[$periodKey]->pluck('pageviews') ?? []),
                clicks: @json($dailyMetricsData[$periodKey]->pluck('clicks') ?? [])
            },
            @endforeach
        };

        // Metric colors
        const metricColors = {
            'commission': { border: 'rgb(139, 92, 246)', bg: 'rgba(139, 92, 246, 0.3)', prefix: '€' },
            'visitors': { border: 'rgb(34, 197, 94)', bg: 'rgba(34, 197, 94, 0.3)', prefix: '' },
            'pageviews': { border: 'rgb(59, 130, 246)', bg: 'rgba(59, 130, 246, 0.3)', prefix: '' },
            'clicks': { border: 'rgb(249, 115, 22)', bg: 'rgba(249, 115, 22, 0.3)', prefix: '' }
        };

        // Initialize charts
        @foreach(['1d', '7d', '30d', '90d', '365d', 'all-time'] as $periodKey)
        (function() {
            const periodKey = '{{ $periodKey }}';
            const ctx = document.getElementById('chart-{{ $periodKey }}');
            if (!ctx) return;

            const currentColors = metricColors[initialChartMetric];
            const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, currentColors.bg);
            gradient.addColorStop(1, currentColors.bg.replace('0.3', '0'));

            charts[periodKey] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartData['{{ $periodKey }}'].dates,
                    datasets: [{
                        label: initialChartMetric.charAt(0).toUpperCase() + initialChartMetric.slice(1),
                        data: chartData['{{ $periodKey }}'][initialChartMetric],
                        borderColor: currentColors.border,
                        backgroundColor: gradient,
                        tension: 0.4,
                        fill: true,
                        pointRadius: 0,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: currentColors.border,
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    aspectRatio: 2.5,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            titleColor: 'rgb(226, 232, 240)',
                            bodyColor: 'rgb(148, 163, 184)',
                            borderColor: currentColors.bg,
                            borderWidth: 1,
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return currentColors.prefix + context.parsed.y.toFixed(initialChartMetric === 'commission' ? 2 : 0);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(100, 116, 139, 0.1)',
                                drawBorder: false
                            },
                            ticks: {
                                color: 'rgb(148, 163, 184)',
                                callback: function(value) {
                                    return currentColors.prefix + value;
                                },
                                padding: 4
                            }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: 'rgb(148, 163, 184)',
                                maxTicksLimit: 8,
                                padding: 4,
                                maxRotation: 0
                            }
                        }
                    },
                    layout: {
                        padding: {
                            top: 10,
                            right: 10,
                            bottom: 0,
                            left: 0
                        }
                    }
                }
            });

            // Update chart function
            window['updateChart' + periodKey.replace('-', '')] = function(metric) {
                const chart = charts[periodKey];
                const colors = {
                    'commission': { border: 'rgb(139, 92, 246)', bg: 'rgba(139, 92, 246, 0.3)', prefix: '€' },
                    'visitors': { border: 'rgb(34, 197, 94)', bg: 'rgba(34, 197, 94, 0.3)', prefix: '' },
                    'pageviews': { border: 'rgb(59, 130, 246)', bg: 'rgba(59, 130, 246, 0.3)', prefix: '' },
                    'clicks': { border: 'rgb(249, 115, 22)', bg: 'rgba(249, 115, 22, 0.3)', prefix: '' }
                };

                const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
                gradient.addColorStop(0, colors[metric].bg);
                gradient.addColorStop(1, colors[metric].bg.replace('0.3', '0'));

                chart.data.datasets[0].data = chartData[periodKey][metric];
                chart.data.datasets[0].borderColor = colors[metric].border;
                chart.data.datasets[0].backgroundColor = gradient;
                chart.data.datasets[0].pointHoverBackgroundColor = colors[metric].border;

                chart.options.scales.y.ticks.callback = function(value) {
                    return colors[metric].prefix + value;
                };
                chart.options.plugins.tooltip.callbacks.label = function(context) {
                    return colors[metric].prefix + context.parsed.y.toFixed(metric === 'commission' ? 2 : 0);
                };

                chart.update();
            };
        })();
        @endforeach
    </script>

    <style>
        [x-cloak] { display: none !important; }

        /* Hide scrollbar maar behoud functionality */
        .scrollbar-hide {
            -ms-overflow-style: none;  /* IE and Edge */
            scrollbar-width: none;  /* Firefox */
        }
        .scrollbar-hide::-webkit-scrollbar {
            display: none;  /* Chrome, Safari and Opera */
        }
    </style>

</body>
</html>
