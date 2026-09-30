// Port of app/(tenant)/[companySlug]/admin/stock/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';

Alpine.data('stockPage', () => ({
    products: [],
    loading: true,
    showHeader: true,
    previewUrl: null,
    previewingId: null,
    searchTerm: '',
    currentPage: 1,
    totalPages: 1,
    totalItems: 0,
    limit: 10,

    init() {
        // useEffect(() => { setTimeout(() => fetchProducts(1, searchTerm), 500) }, [searchTerm]) — also on mount
        const schedule = () => {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.fetchProducts(1, this.searchTerm), 500);
        };
        this.$watch('searchTerm', schedule);
        schedule();
    },

    async fetchProducts(page = 1, search = '') {
        try {
            this.loading = true;
            const response = await api.get('/admin/products', { params: { page, limit: this.limit, search: search || undefined } });
            this.products = response.data.data;
            this.totalPages = response.data.pages;
            this.totalItems = response.data.total;
            this.currentPage = response.data.page;
        } catch (err) {
            console.error('Failed to load products', err);
            toast.error('Failed to load products');
        } finally {
            this.loading = false;
        }
    },

    handlePageChange(newPage) {
        if (newPage >= 1 && newPage <= this.totalPages) this.fetchProducts(newPage, this.searchTerm);
    },

    get pageStockUnits() { return this.products.reduce((acc, p) => acc + (p.stock || 0), 0); },

    async handleDownloadRecord(product) {
        try {
            toast.info('Preparing record...');
            const response = await api.get(`/admin/labs/invoice/reagent/${product.id}`, { params: { showHeader: this.showHeader }, responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Inventory-Record-${product.id}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.parentNode?.removeChild(link);
            window.URL.revokeObjectURL(url);
        } catch (err) {
            toast.error('Failed to download record');
        }
    },

    async handlePreviewRecord(product) {
        this.previewingId = product.id;
        try {
            toast.info('Generating preview...');
            const response = await api.get(`/admin/labs/invoice/reagent/${product.id}`, { params: { showHeader: this.showHeader }, responseType: 'blob' });
            this.previewUrl = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        } catch (err) {
            toast.error('Failed to generate preview');
        } finally {
            this.previewingId = null;
        }
    },

    downloadPreview() {
        const link = document.createElement('a');
        link.href = this.previewUrl;
        link.setAttribute('download', 'Inventory-Record.pdf');
        document.body.appendChild(link);
        link.click();
        link.remove();
    },

    closePreview() {
        URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = null;
    },
}));
