// Port of app/(tenant)/[companySlug]/admin/sales-lab/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';

Alpine.data('salesLab', () => ({
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
    labName: '',
    amountGiven: '',
    remarks: '',
    previousBalancePayment: '',
    isProcessing: false,
    customerSearchResults: [],
    selectedCustomerId: null,
    selectedCustomerData: null,
    isSearchingCustomer: false,
    showSearchDropdown: false,
    searchSource: 'name',
    lastOrderId: null,
    isDownloadingInvoice: false,
    previewUrl: null,
    isPreviewing: false,
    showHeader: true,

    init() {
        // useEffect(() => { fetchProducts(); fetchCategories(); }, [fetchProducts, fetchCategories])
        this.$watch("appliedSearch + '|' + selectedCategory + '|' + currentPage", () => { this.fetchProducts(); this.fetchCategories(); });
        this.fetchProducts();
        this.fetchCategories();
        // 400ms search debounce (also runs once on mount)
        const debounceSearch = () => {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => { this.appliedSearch = this.search; this.currentPage = 1; }, 400);
        };
        this.$watch('search', debounceSearch);
        debounceSearch();
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

    invoiceRequest(orderId) {
        return api.get('/admin/labs/invoice/download', { params: { orderId, showHeader: this.showHeader }, responseType: 'blob' });
    },

    async handleDownloadInvoice(orderId) {
        this.isDownloadingInvoice = true;
        try {
            const response = await this.invoiceRequest(orderId);
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Invoice-${orderId}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
            toast.success('Invoice downloaded successfully');
        } catch (err) {
            console.error('Download error', err);
            toast.error('Failed to download invoice');
        } finally {
            this.isDownloadingInvoice = false;
        }
    },

    async handlePreviewInvoice(orderId) {
        this.isPreviewing = true;
        try {
            toast.info(`Generating preview for invoice #${orderId}...`);
            const response = await this.invoiceRequest(orderId);
            this.previewUrl = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        } catch (err) {
            toast.error('Preview failed');
        } finally {
            this.isPreviewing = false;
        }
    },

    downloadPreview() {
        const link = document.createElement('a');
        link.href = this.previewUrl;
        link.setAttribute('download', `Invoice-${this.lastOrderId}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
    },

    closePreview() {
        URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = null;
    },

    unlinkCustomer() {
        if (this.selectedCustomerId) {
            this.selectedCustomerId = null;
            this.selectedCustomerData = null;
        }
    },

    async searchCustomers(query, minLength) {
        if (query.length < minLength) {
            this.customerSearchResults = [];
            this.showSearchDropdown = false;
            return;
        }
        try {
            this.isSearchingCustomer = true;
            const response = await api.get('/admin/labs/customers', { params: { search: query } });
            this.customerSearchResults = response.data.data || response.data;
            this.showSearchDropdown = true;
        } catch (err) {
            console.error('Failed to search customers', err);
        } finally {
            this.isSearchingCustomer = false;
        }
    },

    handleCustomerSearch(query) {
        this.customerName = query;
        this.searchSource = 'name';
        this.unlinkCustomer();
        return this.searchCustomers(query, 2);
    },

    handlePhoneSearch(query) {
        this.customerPhone = query;
        this.searchSource = 'phone';
        this.unlinkCustomer();
        return this.searchCustomers(query, 3);
    },

    selectCustomer(customer) {
        this.customerName = customer.name;
        this.customerPhone = customer.phone;
        this.labName = customer.labName || '';
        this.selectedCustomerId = customer.id;
        this.selectedCustomerData = customer;
        this.showSearchDropdown = false;
        toast.success(`Linked to ${customer.name}`);
    },

    addToCart(product) {
        const existing = this.cart.find((item) => item.id === product.id);
        if (existing) {
            if (existing.cartQuantity >= product.stock) {
                toast.error(`Only ${product.stock} units available`);
                return;
            }
            this.cart = this.cart.map((item) => (item.id === product.id ? { ...item, cartQuantity: item.cartQuantity + 1 } : item));
        } else {
            if (product.stock <= 0) {
                toast.error('Item out of stock');
                return;
            }
            this.cart = [...this.cart, { ...product, cartQuantity: 1, discountPercentage: 0, discountAmount: 0, discountType: 'percent' }];
        }
    },

    updateDiscount(id, value, type) {
        this.cart = this.cart.map((item) => {
            if (item.id !== id) return item;
            const lineTotal = parseFloat(item.price) * item.cartQuantity;
            if (type === 'percent') {
                const val = Math.min(100, Math.max(0, value));
                return { ...item, discountPercentage: val, discountAmount: Math.round((lineTotal * val) / 100), discountType: 'percent' };
            }
            const val = Math.max(0, value);
            return { ...item, discountPercentage: lineTotal > 0 ? (val / lineTotal) * 100 : 0, discountAmount: val, discountType: 'amount' };
        });
    },

    toggleDiscountType(item) {
        this.updateDiscount(item.id, item.discountType === 'percent' ? item.discountAmount : item.discountPercentage, item.discountType === 'percent' ? 'amount' : 'percent');
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

    handleManualQuantityChange(id, value) {
        if (value === '') {
            this.cart = this.cart.map((item) => (item.id === id ? { ...item, cartQuantity: 0 } : item));
            return;
        }
        const val = parseInt(value);
        if (isNaN(val)) return;
        this.cart = this.cart.map((item) => {
            if (item.id !== id) return item;
            if (val > item.stock) {
                toast.error(`Only ${item.stock} units available`);
                return { ...item, cartQuantity: item.stock };
            }
            return { ...item, cartQuantity: Math.max(0, val) };
        });
    },

    removeFromCart(id) { this.cart = this.cart.filter((item) => item.id !== id); },

    itemSavings(item) {
        return Math.round(item.discountType === 'amount' ? item.discountAmount : (parseFloat(item.price) * item.cartQuantity * item.discountPercentage) / 100);
    },

    get subtotal() { return Math.round(this.cart.reduce((acc, item) => acc + parseFloat(item.price) * item.cartQuantity, 0)); },
    get totalDiscountAmount() {
        return Math.round(this.cart.reduce((acc, item) => {
            if (item.discountType === 'amount') return acc + item.discountAmount;
            return acc + (parseFloat(item.price) * item.cartQuantity * item.discountPercentage) / 100;
        }, 0));
    },
    get grandTotal() { return this.subtotal - this.totalDiscountAmount; },
    get changeDue() { return Math.round((parseFloat(this.amountGiven) || 0) - this.grandTotal); },
    get categoryOptions() { return [{ value: 'all', label: 'Global Catalog' }, ...this.categories.map((c) => ({ value: c.id.toString(), label: c.name }))]; },
    isExpired(p) { return p.expiryDate && new Date(p.expiryDate) < new Date(); },

    async handleCheckout() {
        if (this.cart.length === 0) { toast.error('Cart is empty'); return; }
        if ((!this.amountGiven || parseFloat(this.amountGiven) < 0) && !this.selectedCustomerId) {
            toast.error('Please enter the amount received from the customer');
            return;
        }
        if (this.changeDue < 0 && !this.selectedCustomerId) {
            toast.error(`Non-registered customers must pay the full amount. Total is PKR ${this.grandTotal.toLocaleString()}`);
            return;
        }
        try {
            this.isProcessing = true;
            const payload = {
                customerName: this.customerName.trim() || 'Walk-In Customer',
                phone: this.customerPhone.trim() || null,
                items: this.cart.map((item) => ({
                    productId: item.id,
                    quantity: item.cartQuantity,
                    discountPercentage: item.discountType === 'percent' ? item.discountPercentage : undefined,
                    discountAmount: item.discountType === 'amount' ? item.discountAmount : undefined,
                })),
                amountTendered: Math.round(parseFloat(this.amountGiven) || 0),
                changeDue: this.changeDue,
                registeredCustomerId: this.selectedCustomerId,
                previousBalancePayment: Math.round(parseFloat(this.previousBalancePayment) || 0),
                remarks: this.remarks.trim() || null,
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
        this.labName = '';
        this.selectedCustomerId = null;
        this.selectedCustomerData = null;
        this.amountGiven = '';
        this.remarks = '';
        this.previousBalancePayment = '';
        this.lastOrderId = null;
    },
}));
