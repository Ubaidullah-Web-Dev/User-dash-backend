// Port of app/(tenant)/[companySlug]/admin/pos/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';

const money = (n) => n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

Alpine.data('posPage', () => ({
    products: [],
    categories: [],
    search: '',
    appliedSearch: '',
    loadingProducts: false,
    currentPage: 1,
    totalPages: 1,
    totalItems: 0,
    pageSize: 10,
    selectedCategory: 'all',
    cart: [],
    customerName: '',
    customerPhone: '',
    amountGiven: '',
    discountPercentage: '',
    isProcessing: false,
    lastOrderId: null,
    money,

    init() {
        this.fetchCategories();
        this.$watch("appliedSearch + '|' + selectedCategory + '|' + currentPage", () => this.fetchProducts());
        this.fetchProducts();
    },

    async fetchCategories() {
        try {
            const response = await api.get('/categories');
            this.categories = response.data;
        } catch (err) {
            console.error('Failed to load categories', err);
        }
    },

    async fetchProducts() {
        try {
            this.loadingProducts = true;
            const response = await api.get('/admin/products', {
                params: {
                    search: this.appliedSearch || undefined,
                    category: this.selectedCategory === 'all' ? undefined : this.selectedCategory,
                    page: this.currentPage,
                    limit: this.pageSize,
                },
            });
            this.products = response.data.data;
            this.totalPages = response.data.totalPages || Math.ceil(response.data.total / this.pageSize);
            this.totalItems = response.data.total;
        } catch (err) {
            toast.error('Failed to sync inventory');
        } finally {
            this.loadingProducts = false;
        }
    },

    handleSearchSubmit() {
        this.appliedSearch = this.search;
        this.currentPage = 1;
    },

    addToCart(product) {
        const existing = this.cart.find((item) => item.id === product.id);
        if (existing) {
            if (existing.cartQuantity >= product.stock) {
                toast.error(`Only ${product.stock} units available in stock`);
                return;
            }
            this.cart = this.cart.map((item) => (item.id === product.id ? { ...item, cartQuantity: item.cartQuantity + 1 } : item));
        } else {
            if (product.stock <= 0) {
                toast.error('Item out of stock');
                return;
            }
            this.cart = [...this.cart, { ...product, cartQuantity: 1 }];
        }
    },

    updateQuantity(id, delta) {
        this.cart = this.cart.map((item) => {
            if (item.id !== id) return item;
            const newQuantity = item.cartQuantity + delta;
            if (newQuantity <= 0) return item;
            if (newQuantity > item.stock) {
                toast.error(`Only ${item.stock} units available`);
                return item;
            }
            return { ...item, cartQuantity: newQuantity };
        });
    },

    removeFromCart(id) { this.cart = this.cart.filter((item) => item.id !== id); },

    get subtotal() { return this.cart.reduce((acc, item) => acc + parseFloat(item.price) * item.cartQuantity, 0); },
    get discountValue() { return (this.subtotal * (parseFloat(this.discountPercentage) || 0)) / 100; },
    get total() { return this.subtotal - this.discountValue; },
    get changeDue() { return this.amountGiven ? parseFloat(this.amountGiven) - this.total : 0; },
    get checkoutDisabled() { return this.isProcessing || this.cart.length === 0 || !!(this.amountGiven && parseFloat(this.amountGiven) < this.total); },
    get rangeStart() { return Math.min((this.currentPage - 1) * this.pageSize + 1, this.totalItems); },
    get rangeEnd() { return Math.min(this.currentPage * this.pageSize, this.totalItems); },
    get categoryOptions() { return [{ value: 'all', label: 'All Items' }, ...this.categories.map((c) => ({ value: c.id.toString(), label: c.name }))]; },

    async handleCheckout() {
        if (this.cart.length === 0) { toast.error('Cart is empty'); return; }
        if (this.amountGiven && parseFloat(this.amountGiven) < this.total) { toast.error('Amount given is less than total'); return; }
        try {
            this.isProcessing = true;
            const changeDue = this.changeDue;
            const payload = {
                customerName: this.customerName.trim() || 'Walk-In Customer',
                phone: this.customerPhone.trim() || null,
                items: this.cart.map((item) => ({ productId: item.id, quantity: item.cartQuantity })),
                amountTendered: this.amountGiven ? parseFloat(this.amountGiven) : null,
                changeDue: changeDue > 0 ? changeDue : 0,
                discountPercentage: parseFloat(this.discountPercentage) || 0,
                discountAmount: this.discountValue,
            };
            const response = await api.post('/admin/orders/walk-in', payload);
            toast.success('Transaction Complete');
            this.lastOrderId = response.data.orderId;
            this.fetchProducts();
        } catch (err) {
            toast.error(err.response?.data?.message || 'Checkout failed');
        } finally {
            this.isProcessing = false;
        }
    },

    startNewSale() {
        this.cart = [];
        this.customerName = '';
        this.customerPhone = '';
        this.amountGiven = '';
        this.discountPercentage = '';
        this.lastOrderId = null;
    },

    async handleDownloadInvoice() {
        if (!this.lastOrderId) return;
        try {
            const response = await api.get('/invoice/download', { params: { orderId: this.lastOrderId }, responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `invoice-${this.lastOrderId}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.parentNode?.removeChild(link);
            window.URL.revokeObjectURL(url);
            toast.success('Invoice downloaded successfully');
        } catch (err) {
            console.error('Download error:', err);
            const data = err.response?.data;
            if (data && typeof data === 'object') toast.error(`Download failed: ${data.message || 'Unknown error'}`);
            else toast.error('Failed to download invoice');
        }
    },
}));
