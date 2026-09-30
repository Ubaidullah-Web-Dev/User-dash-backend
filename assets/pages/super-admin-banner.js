// Port of app/super-admin/banner/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';

Alpine.data('bannerManagement', () => ({
    companies: [],
    loading: true,
    toggling: null,

    init() { this.fetchCompanies(); },

    async fetchCompanies() {
        this.loading = true;
        try {
            const response = await api.get('/super-admin/companies');
            this.companies = response.data;
        } catch (error) {
            console.error('Failed to fetch companies', error);
            toast.error('Failed to load banner states');
        } finally {
            this.loading = false;
        }
    },

    isEnabled(company) { return company.settings_json?.show_alphasoft_banner === '1'; },

    async handleToggleBanner(companyId, currentStatus) {
        this.toggling = companyId;
        try {
            await api.post(`/super-admin/companies/${companyId}/toggle-banner`, { enabled: !currentStatus });
            this.companies = this.companies.map((c) => (c.id === companyId
                ? { ...c, settings_json: { ...c.settings_json, show_alphasoft_banner: !currentStatus ? '1' : '0' } }
                : c));
            toast.success(`Banner updated for ${this.companies.find((c) => c.id === companyId)?.name}`);
        } catch (error) {
            toast.error('Failed to update banner setting');
        } finally {
            this.toggling = null;
        }
    },
}));
