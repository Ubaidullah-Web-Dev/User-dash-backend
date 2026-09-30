// Port of app/(tenant)/[companySlug]/admin/dashboard-lab/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { router, params } from '../lib/router.js';

Alpine.data('labDashboard', () => ({
    stats: null,
    salesTimeframe: 'today',
    pendingTimeframe: 'total',
    companySlug: params().companySlug,

    init() {
        api.get('/admin/labs/dashboard')
            .then((response) => { this.stats = response.data; })
            .catch((err) => console.error('Failed to load lab dashboard data', err));
    },

    get statCards() {
        const s = this.stats;
        return [
            { label: 'Total Reagents', value: s?.totalProducts || '0', color: 'from-blue-500 to-cyan-500', icon: 'package' },
            { label: 'Stock Value', value: `PKR ${s?.stockValue?.toLocaleString() || '0'}`, color: 'from-emerald-500 to-teal-600', icon: 'activity' },
            { label: 'Sales', value: `PKR ${(this.salesTimeframe === 'today' ? s?.todaySales : s?.totalSales)?.toLocaleString() || '0'}`, color: 'from-indigo-500 to-purple-600', icon: 'shopping-bag', timeframeKey: 'salesTimeframe' },
            { label: 'Pending', value: `PKR ${(this.pendingTimeframe === 'today' ? s?.todayPending : s?.totalPending)?.toLocaleString() || '0'}`, color: 'from-orange-500 to-red-600', icon: 'wallet', timeframeKey: 'pendingTimeframe' },
            { label: 'Low Stock', value: s?.lowStockCount || '0', color: s?.lowStockCount && s.lowStockCount > 0 ? 'from-red-500 to-orange-600' : 'from-slate-500 to-slate-600', icon: 'activity' },
        ];
    },

    fontSizeClass(value) {
        return value.toString().length > 12 ? 'text-xl md:text-2xl' : 'text-2xl md:text-3xl';
    },

    go(path) { router.push(`/${this.companySlug}${path}`); },
}));
