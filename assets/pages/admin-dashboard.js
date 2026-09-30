// Port of app/(tenant)/[companySlug]/admin/dashboard/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { auth } from '../lib/auth.js';
import { router, params } from '../lib/router.js';

Alpine.data('adminDashboard', () => ({
    adminData: null,

    init() {
        const roles = auth.user?.roles || [];
        if (!roles.includes('ROLE_ADMIN') && !roles.includes('ROLE_SUPER_ADMIN')) {
            router.push(`/${params().companySlug}`);
        }
        api.get('/admin/dashboard')
            .then((response) => { this.adminData = response.data; })
            .catch((err) => console.error('Failed to load admin data', err));
    },

    get statCards() {
        return [
            { label: 'Total Products', value: this.adminData?.stats?.total_products || '0', color: 'from-primary to-blue-600', icon: '📦' },
            { label: 'Active Users', value: this.adminData?.stats?.total_users || '0', color: 'from-purple-500 to-pink-600', icon: '👥' },
            { label: 'Total Sales', value: '$0', color: 'from-emerald-500 to-teal-600', icon: '💰' },
            { label: 'Pending', value: '0', color: 'from-amber-500 to-orange-600', icon: '⏳' },
        ];
    },
}));
