// Port of app/(tenant)/[companySlug]/profile/my-ads/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { auth } from '../lib/auth.js';
import { router } from '../lib/router.js';

Alpine.data('myAdsPage', () => ({
    ads: [],
    loading: true,

    async init() {
        if (!auth.isAuthenticated) {
            router.push('/login');
            return;
        }
        try {
            const response = await api.get('/products/my-ads');
            this.ads = response.data;
        } catch (err) {
            console.error('Failed to fetch ads', err);
            toast.error('Failed to load ads');
        } finally {
            this.loading = false;
        }
    },
}));
