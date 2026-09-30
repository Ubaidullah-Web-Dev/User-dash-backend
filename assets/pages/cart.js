// Port of app/(tenant)/[companySlug]/cart/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { auth } from '../lib/auth.js';
import { router } from '../lib/router.js';

Alpine.data('cartPage', () => ({
    items: [],
    loading: true,

    init() {
        if (!auth.isAuthenticated) {
            router.push('/login');
            return;
        }
        this.fetchCart();
    },

    async fetchCart() {
        try {
            const response = await api.get('/cart');
            this.items = response.data;
        } catch (err) {
            console.error('Failed to fetch cart', err);
        } finally {
            this.loading = false;
        }
    },

    async handleRemove(id) {
        try {
            await api.delete(`/cart/${id}`);
            this.items = this.items.filter((item) => item.id !== id);
            toast.success('Item removed');
        } catch (err) {
            console.error(err);
            toast.error('Cleanup failed');
        }
    },

    async handleToggleSave(id) {
        try {
            await api.patch(`/cart/${id}/toggle-save`);
            this.fetchCart();
            toast.success('Cart updated');
        } catch (err) {
            console.error(err);
            toast.error('Update failed');
        }
    },

    get cartItems() { return this.items.filter((i) => !i.isSavedForLater); },
    get savedItems() { return this.items.filter((i) => i.isSavedForLater); },
    get subtotal() { return this.cartItems.reduce((acc, item) => acc + parseFloat(item.productPrice) * item.quantity, 0); },
    get shipping() { return this.subtotal > 500 ? 0 : 25; },
    get total() { return this.subtotal + this.shipping; },
}));
