<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sites - Affiliate Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-[#1a1d2e] min-h-screen text-slate-100 font-sans antialiased">

    <x-sidebar />

    <!-- Main Canvas -->
    <main class="ml-20 p-10" x-data="{
        period: '{{ $period }}',
        statusFilter: '{{ $statusFilter }}',
        periods: {
            '7d': { label: '7d' },
            '30d': { label: '30d' },
            '90d': { label: '90d' },
            '365d': { label: '365d' },
            'all-time': { label: 'All-time' }
        },

        changePeriod(newPeriod) {
            const params = new URLSearchParams();
            params.append('period', newPeriod);
            params.append('status_filter', this.statusFilter);
            window.location.href = '/sites?' + params.toString();
        }
    }">

        <!-- Header with Period Selector -->
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-4xl font-light text-slate-100 mb-2">Sites</h1>
                <p class="text-slate-400">Overview van alle {{ count($sites) }} affiliate sites</p>
            </div>

            <!-- Period Selector - Top Right -->
            <div class="flex gap-2">
                <template x-for="(periodData, periodKey) in periods" :key="periodKey">
                    <button
                        @click="changePeriod(periodKey)"
                        :class="period === periodKey ? 'bg-lavender text-white' : 'bg-[#252839] text-slate-400 hover:bg-[#2d3048]'"
                        class="px-4 py-2 rounded-lg transition-all duration-200 text-sm font-medium"
                        x-text="periodData.label"
                    ></button>
                </template>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-5 gap-4 mb-8">
            <div class="bg-[#252839] backdrop-blur-light rounded-xl p-6 shadow-lg border border-slate-700/20">
                <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Total Commission</p>
                <p class="text-3xl font-light text-white">€{{ number_format($totals['commission'], 2) }}</p>
            </div>
            <div class="bg-[#252839] backdrop-blur-light rounded-xl p-6 shadow-lg border border-slate-700/20">
                <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Total Orders</p>
                <p class="text-3xl font-light text-white">{{ number_format($totals['orders']) }}</p>
            </div>
            <div class="bg-[#252839] backdrop-blur-light rounded-xl p-6 shadow-lg border border-slate-700/20">
                <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Total Clicks</p>
                <p class="text-3xl font-light text-white">{{ number_format($totals['clicks']) }}</p>
            </div>
            <div class="bg-[#252839] backdrop-blur-light rounded-xl p-6 shadow-lg border border-slate-700/20">
                <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Total Visitors</p>
                <p class="text-3xl font-light text-white">{{ number_format($totals['visitors']) }}</p>
            </div>
            <div class="bg-[#252839] backdrop-blur-light rounded-xl p-6 shadow-lg border border-slate-700/20">
                <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Total Pageviews</p>
                <p class="text-3xl font-light text-white">{{ number_format($totals['pageviews']) }}</p>
            </div>
        </div>

        <!-- Sites Table -->
        <div class="bg-[#252839] backdrop-blur-light rounded-xl shadow-lg border border-slate-700/20 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-slate-700/30">
                        <th class="text-left p-4 text-xs font-medium text-slate-400 uppercase tracking-wider">Site</th>
                        <th class="text-right p-4 text-xs font-medium text-slate-400 uppercase tracking-wider">Commission</th>
                        <th class="text-right p-4 text-xs font-medium text-slate-400 uppercase tracking-wider">% of Total</th>
                        <th class="text-right p-4 text-xs font-medium text-slate-400 uppercase tracking-wider">Orders</th>
                        <th class="text-right p-4 text-xs font-medium text-slate-400 uppercase tracking-wider">Clicks</th>
                        <th class="text-right p-4 text-xs font-medium text-slate-400 uppercase tracking-wider">Visitors</th>
                        <th class="text-right p-4 text-xs font-medium text-slate-400 uppercase tracking-wider">RPV</th>
                        <th class="text-right p-4 text-xs font-medium text-slate-400 uppercase tracking-wider">Conv. Rate</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sites as $site)
                    <tr class="border-b border-slate-700/20 hover:bg-[#1a1d2e]/50 transition-colors cursor-pointer"
                        onclick="window.location.href='/sites/{{ $site->id }}'">
                        <td class="p-4">
                            <div class="flex flex-col">
                                <span class="text-slate-100 font-medium">{{ $site->name }}</span>
                                <span class="text-xs text-slate-500">{{ $site->domain }}</span>
                            </div>
                        </td>
                        <td class="p-4 text-right">
                            <span class="text-slate-100 font-semibold">€{{ number_format($site->commission, 2) }}</span>
                        </td>
                        <td class="p-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <div class="w-16 h-1.5 bg-slate-700/30 rounded-full overflow-hidden">
                                    <div class="h-full bg-lavender rounded-full" style="width: {{ min($site->commission_pct, 100) }}%"></div>
                                </div>
                                <span class="text-xs text-slate-400 w-10">{{ number_format($site->commission_pct, 1) }}%</span>
                            </div>
                        </td>
                        <td class="p-4 text-right text-slate-300">{{ number_format($site->orders) }}</td>
                        <td class="p-4 text-right text-slate-300">{{ number_format($site->clicks) }}</td>
                        <td class="p-4 text-right text-slate-300">{{ number_format($site->visitors) }}</td>
                        <td class="p-4 text-right text-slate-300">€{{ number_format($site->rpv, 3) }}</td>
                        <td class="p-4 text-right text-slate-300">{{ number_format($site->conversion_rate * 100, 2) }}%</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

    </main>

</body>
</html>
