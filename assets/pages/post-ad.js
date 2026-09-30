// Port of app/(tenant)/[companySlug]/marketplace/post-ad/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { auth } from '../lib/auth.js';
import { router, params } from '../lib/router.js';

Alpine.data('postAdPage', () => ({
    categories: [],
    loading: false,
    formData: { name: '', description: '', price: '', stock: '1', unit: '', category_id: '', images: [''] },

    async init() {
        if (!auth.isAuthenticated) {
            router.push('/login');
        }
        try {
            const response = await api.get('/categories');
            this.categories = response.data;
        } catch (err) {
            console.error('Failed to fetch categories', err);
        }
    },

    get categoryOptions() { return this.categories.map((c) => ({ value: c.id.toString(), label: c.name })); },
    addImageField() { this.formData.images = [...this.formData.images, '']; },
    removeImageField(index) { this.formData.images = this.formData.images.filter((_, i) => i !== index); },

    async handleSubmit() {
        this.loading = true;
        try {
            const cleanImages = this.formData.images.filter((img) => img.trim() !== '');
            await api.post('/products', {
                ...this.formData,
                images: cleanImages,
                price: parseFloat(this.formData.price).toString(),
                stock: parseInt(this.formData.stock),
                category_id: parseInt(this.formData.category_id),
                unit: this.formData.unit.trim(),
            });
            toast.success('Ad Published!', { description: 'Your product is now live on the marketplace.' });
            router.push(`/${params().companySlug}/marketplace`);
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Something went wrong.';
            toast.error('Submission Failed', { description: message });
        } finally {
            this.loading = false;
        }
    },
}));
