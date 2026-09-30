// Port of app/(tenant)/[companySlug]/checkout/address/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { auth } from '../lib/auth.js';
import { router, params } from '../lib/router.js';

Alpine.data('checkoutAddressPage', () => ({
    loading: false,
    // useState({ name: user?.name || '' }) ran while AuthContext was still loading → always ''
    formData: { address: '', phone: '', name: '' },

    init() {
        if (!auth.isAuthenticated) router.push(`/${params().companySlug}/login`);
    },

    async handleSubmit() {
        const { companySlug } = params();
        const f = this.formData;
        if (!f.name || f.name.length < 2) { toast.error('Please enter a valid full name'); return; }
        if (/^\d+$/.test(f.name)) { toast.error('Name cannot be only numbers'); return; }
        if (!f.address || f.address.length < 5) { toast.error('Please enter a more detailed address'); return; }
        if (/^\d+$/.test(f.address)) { toast.error('Address cannot be only numbers'); return; }
        const phoneDigits = f.phone.replace(/\D/g, '');
        if (phoneDigits.length === 0) { toast.error('Please enter a phone number'); return; }
        if (phoneDigits.length > 11) { toast.error('Phone number cannot be more than 11 digits'); return; }

        this.loading = true;
        try {
            const response = await api.post('/orders', { address: f.address, phone: f.phone, name: f.name });
            toast.success('Order placed successfully!');
            router.push(`/${companySlug}/checkout/summary/${response.data.id}`);
        } catch (err) {
            console.error(err);
            toast.error(err.response?.data?.message || 'Failed to place order');
        } finally {
            this.loading = false;
        }
    },
}));
