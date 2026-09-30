// Port of app/super-admin/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';

Alpine.data('superAdminDashboard', () => ({
    companiesCount: null,
    loading: true,
    saving: false,
    formData: { name: '', email: '', password: '' },

    init() {
        api.get('/super-admin/companies')
            .then((res) => { this.companiesCount = res.data.length; })
            .catch((err) => console.error(err))
            .finally(() => { this.loading = false; });
    },

    get stats() {
        return [
            { label: 'Infrastructure Nodes', value: this.loading ? '...' : `${this.companiesCount} Global`, icon: 'activity', color: 'text-emerald-500', bg: 'bg-emerald-500/5' },
            { label: 'Privileged Access', value: 'Root Active', icon: 'users', color: 'text-blue-500', bg: 'bg-blue-500/5' },
            { label: 'Data Isolation', value: 'Encrypted', icon: 'database', color: 'text-purple-500', bg: 'bg-purple-500/5' },
            { label: 'Security Layer', value: 'Active', icon: 'lock', color: 'text-primary', bg: 'bg-primary/5' },
        ];
    },

    async handleCreateAdmin() {
        if (!this.formData.name || !this.formData.email || !this.formData.password) {
            toast.error('All authorization fields are required');
            return;
        }
        this.saving = true;
        try {
            await api.post('/super-admin/admins', this.formData);
            toast.success('New Super Admin entry provisioned successfully');
            this.formData = { name: '', email: '', password: '' };
        } catch (err) {
            console.error(err);
            toast.error('Operator provisioning failed');
        } finally {
            this.saving = false;
        }
    },
}));
