// Port of admin/customers-lab/CustomerForm.tsx + new/page.tsx + [id]/edit/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { router, params } from '../lib/router.js';

Alpine.data('customerFormPage', () => ({
    id: params().id || null,
    isEditing: !!params().id,
    pageLoading: !!params().id,
    customer: null,
    loading: false,
    formData: { name: '', phone: '', labName: '', city: '', address: '', remainingBalance: 0 },
    paymentAmount: '',

    async init() {
        if (!this.isEditing) return;
        try {
            const response = await api.get(`/admin/labs/customers/${this.id}`);
            this.customer = response.data;
            const d = this.customer || {};
            this.formData = {
                name: d.name || '', phone: d.phone || '', labName: d.labName || '', city: d.city || '',
                address: d.address || '', remainingBalance: d.remainingBalance || 0,
            };
        } catch (err) {
            console.error('Failed to fetch customer', err);
        } finally {
            this.pageLoading = false;
        }
    },

    get pAmount() { return Math.round(parseFloat(this.paymentAmount) || 0); },
    get resultingBalance() { return (this.formData.remainingBalance || 0) - this.pAmount; },

    async handleSubmit() {
        this.loading = true;
        const currentBalance = this.formData.remainingBalance || 0;
        const pAmount = this.pAmount;
        if (this.isEditing && pAmount > currentBalance) {
            toast.error(`Payment exceeds pending balance. Maximum allowed: PKR ${currentBalance}`);
            this.loading = false;
            return;
        }
        const finalBalance = this.isEditing ? currentBalance - pAmount : Math.round(this.formData.remainingBalance || 0);
        const submissionData = { ...this.formData, remainingBalance: finalBalance };
        try {
            if (this.isEditing && this.customer?.id) {
                await api.put(`/admin/labs/customers/${this.customer.id}`, submissionData);
                toast.success('Customer record updated successfully');
            } else {
                await api.post('/admin/labs/customers', submissionData);
                toast.success('New customer registered successfully');
            }
            router.push(`/${params().companySlug}/admin/customers-lab`);
        } catch (err) {
            console.error(err);
            toast.error(err.response?.data?.message || 'Failed to save customer record');
        } finally {
            this.loading = false;
        }
    },

    back() { router.back(); },
}));
