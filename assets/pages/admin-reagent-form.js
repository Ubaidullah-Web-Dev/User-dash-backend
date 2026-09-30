// Port of admin/reagents/ReagentForm.tsx + new/page.tsx + [id]/edit/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { router, params } from '../lib/router.js';

const EMPTY = {
    name: '', companyName: '', packSize: '', price: '', purchasePrice: '', stock: 0, minimumStock: 0,
    expiryDate: '', batchNumber: '', description: '', isActive: true, isRecommended: false,
};

Alpine.data('reagentFormPage', () => ({
    id: params().id || null,
    isEditing: !!params().id,
    pageLoading: !!params().id,
    reagent: null,
    loading: false,
    savedId: null,
    isSaved: false,
    categories: [],
    formData: { ...EMPTY },
    urlSegment: window.location.pathname.split('/')[1],

    async init() {
        this.fetchCategories();
        if (!this.isEditing) return;
        try {
            const response = await api.get(`/admin/products/${this.id}`);
            this.reagent = response.data;
            this.formData = { ...EMPTY, ...this.reagent };
            this.savedId = this.reagent?.id || null;
        } catch (err) {
            console.error('Failed to load reagent', err);
            toast.error('Failed to load reagent data');
        } finally {
            this.pageLoading = false;
        }
    },

    async fetchCategories() {
        try {
            const response = await api.get('/categories');
            this.categories = response.data;
        } catch (err) {
            console.error('Failed to load categories', err);
            toast.error('Failed to load categories. Please check your connection.');
        }
    },

    // handleChange: number inputs store parseFloat(value) (NaN when cleared), like the React form
    setField(e) {
        const { name, value, type } = e.target;
        this.formData[name] = type === 'number' ? parseFloat(value) : value;
    },

    get categoryId() { return this.formData.category?.id?.toString() || ''; },
    set categoryId(value) { this.formData.category = { id: parseInt(value), name: '' }; },
    get categoryOptions() { return this.categories.map((c) => ({ value: c.id.toString(), label: c.name })); },

    async handleSubmit() {
        const f = this.formData;
        if (!f.category?.id) {
            toast.error('Please select a category');
            return;
        }
        this.loading = true;
        try {
            if (this.isEditing && f.id) {
                const dataToSubmit = {
                    name: f.name,
                    description: f.description,
                    price: Math.round(parseFloat(f.price?.toString() || '0')),
                    purchasePrice: f.purchasePrice ? Math.round(parseFloat(f.purchasePrice.toString())) : undefined,
                    stock: parseInt(f.stock?.toString() || '0'),
                    minimumStock: parseInt(f.minimumStock?.toString() || '0'),
                    categoryId: f.category?.id,
                    companyName: f.companyName,
                    packSize: f.packSize,
                    expiryDate: f.expiryDate,
                    batchNumber: f.batchNumber,
                    isActive: f.isActive,
                    isRecommended: f.isRecommended,
                };
                await api.put(`/admin/products/${f.id}`, dataToSubmit);
                this.savedId = f.id;
                this.isSaved = true;
                toast.success('Reagent updated successfully');
            } else {
                const dataToSubmit = {
                    ...f,
                    category_id: f.category?.id,
                    price: Math.round(parseFloat(f.price?.toString() || '0')),
                    purchasePrice: f.purchasePrice ? Math.round(parseFloat(f.purchasePrice.toString())) : undefined,
                    stock: parseInt(f.stock?.toString() || '0'),
                    minimumStock: parseInt(f.minimumStock?.toString() || '0'),
                };
                const response = await api.post('/products', dataToSubmit);
                this.savedId = response.data.id;
                this.isSaved = true;
                toast.success('Reagent added successfully');
            }
        } catch (err) {
            console.error(err);
            const backendErrors = err.response?.data?.errors;
            if (backendErrors && Array.isArray(backendErrors)) {
                backendErrors.forEach((msg) => toast.error(msg));
            } else {
                toast.error(err.response?.data?.message || 'Failed to save reagent');
            }
        } finally {
            this.loading = false;
        }
    },

    async handleDownloadInvoice() {
        if (!this.savedId) return;
        try {
            toast.info('Preparing your PDF document...');
            const response = await api.get(`/admin/labs/invoice/reagent/${this.savedId}`, { responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Reagent-Invoice-${this.savedId}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.parentNode?.removeChild(link);
            window.URL.revokeObjectURL(url);
        } catch (err) {
            console.error('Download failed', err);
            // Non-blob error bodies arrive already parsed (JSON), which is what the React FileReader branch produced
            const data = err.response?.data;
            if (data && typeof data === 'object' && !(data instanceof Blob)) {
                toast.error(data.message || 'Failed to download invoice');
            } else if (typeof data === 'string') {
                toast.error('Failed to download invoice. Server error.');
            } else {
                toast.error('Failed to download invoice. Please try again.');
            }
        }
    },

    back() { router.back(); },
    done() { router.push(`/${params().companySlug}/admin/reagents`); },
}));
