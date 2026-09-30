// Port of app/(tenant)/[companySlug]/admin/orders/new/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { router, params } from '../lib/router.js';

Alpine.data('newOrderPage', () => ({
    vendors: [],
    vendorSearch: '',
    isSearching: false,
    selectedVendor: null,
    products: [],
    productSearch: '',
    isSearchingProducts: false,
    selectedProduct: null,
    submitting: false,
    draftItems: [],
    currentQuantity: 1,
    currentComment: '',
    showVendorSuggestions: false,
    showProductSuggestions: false,

    init() {
        this.onMouseDown = (event) => {
            if (this.$refs.vendor && !this.$refs.vendor.contains(event.target)) this.showVendorSuggestions = false;
            if (this.$refs.product && !this.$refs.product.contains(event.target)) this.showProductSuggestions = false;
        };
        document.addEventListener('mousedown', this.onMouseDown);

        this.fetchVendors();

        const vendorEffect = () => {
            clearTimeout(this.vendorTimer);
            this.vendorTimer = setTimeout(() => {
                if (this.vendorSearch && (!this.selectedVendor || this.vendorSearch !== this.selectedVendor.name)) this.fetchVendors(this.vendorSearch);
                else if (!this.vendorSearch && this.vendors.length === 0) this.fetchVendors();
            }, 300);
        };
        this.$watch("vendorSearch + '|' + (selectedVendor ? selectedVendor.id : '')", vendorEffect);
        vendorEffect();

        const productEffect = () => {
            clearTimeout(this.productTimer);
            this.productTimer = setTimeout(() => {
                if (this.productSearch && (!this.selectedProduct || this.productSearch !== this.selectedProduct.name)) this.fetchProducts(this.productSearch);
                else if (!this.productSearch && this.selectedVendor) this.fetchProducts();
            }, 300);
        };
        this.$watch("productSearch + '|' + (selectedProduct ? selectedProduct.id : '') + '|' + (selectedVendor ? selectedVendor.id : '')", productEffect);
        productEffect();
    },

    destroy() { document.removeEventListener('mousedown', this.onMouseDown); },

    async fetchVendors(search = '') {
        const trimmed = search.trim();
        this.isSearching = true;
        try {
            const endpoint = trimmed ? `/admin/vendors?search=${encodeURIComponent(trimmed)}` : '/admin/vendors';
            const response = await api.get(endpoint);
            this.vendors = response.data.slice(0, 10);
        } catch (err) {
            toast.error('Failed to load vendors');
        } finally {
            this.isSearching = false;
        }
    },

    async fetchProducts(search = '') {
        if (!this.selectedVendor) return;
        const trimmed = search.trim();
        this.isSearchingProducts = true;
        try {
            const endpoint = trimmed
                ? `/admin/products?category=${this.selectedVendor.category.id}&search=${encodeURIComponent(trimmed)}`
                : `/admin/products?category=${this.selectedVendor.category.id}`;
            const response = await api.get(endpoint);
            const list = Array.isArray(response.data) ? response.data : (response.data.data || []);
            this.products = list.slice(0, 10);
        } catch (err) {
            toast.error('Failed to load products');
        } finally {
            this.isSearchingProducts = false;
        }
    },

    onVendorFocus() {
        this.showVendorSuggestions = true;
        if (!this.vendorSearch && this.vendors.length === 0) this.fetchVendors();
    },
    onVendorInput() {
        this.showVendorSuggestions = true;
        if (this.selectedVendor && this.vendorSearch !== this.selectedVendor.name) this.selectedVendor = null;
    },
    onProductFocus() {
        this.showProductSuggestions = true;
        if (!this.productSearch && this.products.length === 0) this.fetchProducts();
    },
    onProductInput() {
        this.showProductSuggestions = true;
        if (this.selectedProduct && this.productSearch !== this.selectedProduct.name) this.selectedProduct = null;
    },

    handleVendorSelect(vendor) {
        this.selectedVendor = vendor;
        this.vendorSearch = vendor.name;
        this.vendors = [];
        this.draftItems = [];
        this.products = [];
        this.productSearch = '';
        this.selectedProduct = null;
        this.showVendorSuggestions = false;
    },

    handleProductSelect(product) {
        this.selectedProduct = product;
        this.productSearch = product.name;
        this.products = [];
        this.showProductSuggestions = false;
    },

    addToDraft() {
        if (!this.selectedProduct) { toast.error('Please select a product'); return; }
        if (this.currentQuantity < 1) { toast.error('Quantity must be at least 1'); return; }
        const existing = this.draftItems.find((item) => item.productId === this.selectedProduct.id);
        if (existing) {
            this.draftItems = this.draftItems.map((item) => (item.productId === this.selectedProduct.id ? { ...item, quantity: item.quantity + this.currentQuantity } : item));
        } else {
            this.draftItems = [...this.draftItems, { productId: this.selectedProduct.id, productName: this.selectedProduct.name, quantity: this.currentQuantity, comment: this.currentComment }];
        }
        this.selectedProduct = null;
        this.productSearch = '';
        this.fetchProducts();
        this.currentQuantity = 1;
        this.currentComment = '';
        toast.success('Added to order draft');
    },

    updateQuantity(productId, newQuantity) {
        if (newQuantity < 1) return;
        this.draftItems = this.draftItems.map((item) => (item.productId === productId ? { ...item, quantity: newQuantity } : item));
    },

    removeFromDraft(productId) { this.draftItems = this.draftItems.filter((item) => item.productId !== productId); },

    async handleSubmitOrder() {
        if (!this.selectedVendor || this.draftItems.length === 0) return;
        this.submitting = true;
        try {
            await api.post('/admin/vendor-orders/batch', { vendorId: this.selectedVendor.id, items: this.draftItems });
            toast.success('Batch order submitted successfully');
            router.push(`/${params().companySlug}/admin/orders`);
        } catch (err) {
            toast.error(err.response?.data?.message || 'Failed to submit order');
        } finally {
            this.submitting = false;
        }
    },

    toOrders() { router.push(`/${params().companySlug}/admin/orders`); },
}));
