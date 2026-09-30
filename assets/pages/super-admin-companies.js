// Port of app/super-admin/companies/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';

Alpine.data('companiesGovernance', () => ({
    companies: [],
    loading: true,
    saving: false,
    isCreatingCompany: false,
    isCreatingAdmin: false,
    selectedCompanyId: null,
    newCompany: { name: '', slug: '' },
    newAdmin: { email: '', name: '', password: '' },

    init() { this.fetchData(); },

    async fetchData() {
        this.loading = true;
        try {
            const res = await api.get('/super-admin/companies');
            this.companies = res.data;
        } catch (error) {
            console.error('Failed to fetch data', error);
            toast.error('Failed to load companies');
        } finally {
            this.loading = false;
        }
    },

    async handleCreateCompany() {
        this.saving = true;
        try {
            await api.post('/super-admin/companies', this.newCompany);
            toast.success('Company initialized successfully');
            this.isCreatingCompany = false;
            this.newCompany = { name: '', slug: '' };
            this.fetchData();
        } catch (error) {
            toast.error(error.response?.data?.error || 'Company initialization failed');
        } finally {
            this.saving = false;
        }
    },

    async handleDeleteCompany(id) {
        if (!confirm('Are you sure you want to delete this company? All associated data will be removed.')) return;
        try {
            await api.delete(`/super-admin/companies/${id}`);
            toast.success('Company removed');
            this.fetchData();
        } catch (err) {
            toast.error('Removal failed');
        }
    },

    async handleCreateAdmin() {
        if (!this.selectedCompanyId) return;
        this.saving = true;
        try {
            await api.post(`/super-admin/companies/${this.selectedCompanyId}/admins`, this.newAdmin);
            toast.success('Admin created successfully');
            this.isCreatingAdmin = false;
            this.newAdmin = { email: '', name: '', password: '' };
        } catch (error) {
            toast.error(error.response?.data?.error || 'Admin creation failed');
        } finally {
            this.saving = false;
        }
    },

    submitModal() { return this.isCreatingCompany ? this.handleCreateCompany() : this.handleCreateAdmin(); },
    closeModals() { this.isCreatingCompany = false; this.isCreatingAdmin = false; },
}));
