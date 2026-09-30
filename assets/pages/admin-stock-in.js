// Port of admin/stock/StockInForm.tsx + stock/new/page.tsx + stock/[id]/in/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { router, params } from '../lib/router.js';

Alpine.data('stockInForm', () => ({
    initialProductId: params().id || '',
    products: [],
    loading: true,
    selectedProductId: params().id || '',
    quantity: '',
    purchasePrice: '',
    supplierName: '',
    submitting: false,
    isSuccess: false,
    lastEntry: null,
    vendors: [],
    vendorSearch: '',
    isSearchingVendors: false,
    showVendorSuggestions: false,

    init() {
        this.onMouseDown = (event) => {
            if (this.$refs.vendor && !this.$refs.vendor.contains(event.target)) this.showVendorSuggestions = false;
        };
        document.addEventListener('mousedown', this.onMouseDown);

        this.$watch('vendorSearch', () => {
            clearTimeout(this.vendorTimer);
            this.vendorTimer = setTimeout(() => {
                if (this.vendorSearch && this.vendorSearch.length > 0) this.fetchVendors(this.vendorSearch);
            }, 300);
        });

        this.fetchProducts();
    },

    destroy() { document.removeEventListener('mousedown', this.onMouseDown); },

    async fetchProducts() {
        try {
            const response = await api.get('/admin/products', { params: { limit: 200 } });
            this.products = response.data.data;
        } catch (err) {
            console.error('Failed to load products', err);
            toast.error('Failed to load products for selection');
        } finally {
            this.loading = false;
        }
    },

    async fetchVendors(search = '') {
        const trimmed = search.trim();
        this.isSearchingVendors = true;
        try {
            const endpoint = trimmed ? `/admin/vendors?search=${encodeURIComponent(trimmed)}` : '/admin/vendors';
            const response = await api.get(endpoint);
            this.vendors = response.data.slice(0, 10);
        } catch (err) {
            console.error('Failed to load vendors', err);
        } finally {
            this.isSearchingVendors = false;
        }
    },

    onVendorInput() {
        this.supplierName = this.vendorSearch;
        this.showVendorSuggestions = this.vendorSearch.trim().length > 0;
    },

    handleVendorSelect(vendor) {
        this.supplierName = vendor.companyName || vendor.name;
        this.vendorSearch = vendor.companyName || vendor.name;
        this.showVendorSuggestions = false;
    },

    get productOptions() {
        return this.products.map((p) => ({
            value: p.id.toString(), label: p.name, class: 'font-bold py-3 hover:bg-primary/5 cursor-pointer',
            suffix: `(Current: ${p.stock})`, suffixClass: 'text-[10px] font-medium text-muted-foreground ml-2',
        }));
    },

    async handleSubmit() {
        if (!this.selectedProductId || !this.quantity) {
            toast.error('Please select a product and enter quantity');
            return;
        }
        try {
            this.submitting = true;
            await api.post('/admin/labs/stock-in', {
                productId: parseInt(this.selectedProductId),
                quantity: parseInt(this.quantity),
                purchasePrice: this.purchasePrice ? Math.round(parseFloat(this.purchasePrice)) : undefined,
                supplier: this.supplierName,
            });
            this.lastEntry = { productId: this.selectedProductId, quantity: this.quantity, unitPrice: this.purchasePrice, supplier: this.supplierName };
            this.isSuccess = true;
            toast.success('Stock updated successfully');
        } catch (err) {
            console.error(err);
            toast.error(err.response?.data?.message || 'Failed to update stock');
        } finally {
            this.submitting = false;
        }
    },

    async handleDownloadInvoice() {
        if (!this.lastEntry) return;
        try {
            toast.info('Preparing your receipt...');
            const qs = new URLSearchParams({
                productId: this.lastEntry.productId, quantity: this.lastEntry.quantity,
                unitPrice: this.lastEntry.unitPrice, supplier: this.lastEntry.supplier,
            });
            const response = await api.get(`/admin/labs/invoice/stock-in?${qs.toString()}`, { responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Stock-Entry-${this.lastEntry.productId}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.parentNode?.removeChild(link);
            window.URL.revokeObjectURL(url);
        } catch (err) {
            console.error('Download failed', err);
            const data = err.response?.data;
            if (data && typeof data === 'object') toast.error(data.message || 'Failed to download receipt');
            else toast.error('Failed to download receipt.');
        }
    },

    toStock() { router.push(`/${params().companySlug}/admin/stock`); },
}));
