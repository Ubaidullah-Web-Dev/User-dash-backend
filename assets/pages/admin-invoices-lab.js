// Port of app/(tenant)/[companySlug]/admin/invoices-lab/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { debounce } from '../lib/utils.js';

Alpine.data('invoicesLab', () => ({
    invoices: [],
    loading: true,
    search: '',
    page: 1,
    totalPages: 1,
    totalItems: 0,
    previewUrl: null,
    previewing: false,
    showHeader: true,
    isEditModalOpen: false,
    editingInvoice: null,
    editLoading: false,
    saving: false,
    availableProducts: [],
    isProductPickerOpen: false,
    productSearch: '',
    pickerProducts: [],
    loadingPickerProducts: false,

    init() {
        this.debouncedSearch = debounce((query) => { this.page = 1; this.fetchInvoices(1, query); }, 500);
        this.fetchPickerProducts = debounce(async (query) => {
            this.loadingPickerProducts = true;
            try {
                const response = await api.get('/admin/products', { params: { search: query || undefined, limit: 50 } });
                this.pickerProducts = response.data.data || [];
            } catch (err) {
                console.error('Failed to fetch picker products', err);
            } finally {
                this.loadingPickerProducts = false;
            }
        }, 300);

        this.$watch('page', () => this.fetchInvoices(this.page, this.search));
        this.fetchInvoices(this.page, this.search);

        api.get('/admin/products', { params: { limit: 100 } })
            .then((response) => { this.availableProducts = response.data.data || []; })
            .catch((err) => console.error('Failed to fetch products', err));

        this.$watch("isProductPickerOpen + '|' + productSearch", () => {
            if (this.isProductPickerOpen) this.fetchPickerProducts(this.productSearch);
            else this.pickerProducts = [];
        });
    },

    async fetchInvoices(currentPage = this.page, searchQuery = this.search) {
        this.loading = true;
        try {
            const response = await api.get('/admin/labs/invoices', { params: { page: currentPage, limit: 10, search: searchQuery } });
            this.invoices = response.data.data || [];
            this.totalPages = response.data.pages || 1;
            this.totalItems = response.data.total || 0;
        } catch (err) {
            console.error('Failed to load invoices', err);
            toast.error('Failed to sync invoice database');
        } finally {
            this.loading = false;
        }
    },

    handleSearchChange() { this.debouncedSearch(this.search); },

    async handleEdit(id) {
        this.editLoading = true;
        this.isEditModalOpen = true;
        try {
            const response = await api.get(`/admin/labs/invoices/${id}`);
            const invoice = response.data;
            invoice.items = (invoice.items || []).map((item) => ({
                ...item,
                discountType: item.discountAmount > 0 && item.discountPercentage === 0 ? 'amount' : 'percent',
            }));
            this.editingInvoice = invoice;
            const merged = [...this.availableProducts];
            invoice.items.forEach((item) => {
                if (!merged.find((p) => p.id === item.productId)) {
                    merged.push({ id: item.productId, name: item.productName || `Product #${item.productId}`, price: item.price });
                }
            });
            this.availableProducts = merged;
        } catch (err) {
            toast.error('Failed to fetch invoice details');
            this.isEditModalOpen = false;
        } finally {
            this.editLoading = false;
        }
    },

    addItem(product) {
        if (!this.editingInvoice) return;
        const newItem = {
            productId: product.id, productName: product.name, quantity: 1, price: parseFloat(product.price),
            discountPercentage: 0, discountAmount: 0, discountType: 'percent',
        };
        if (!this.availableProducts.find((p) => p.id === product.id)) this.availableProducts = [...this.availableProducts, product];
        this.editingInvoice = { ...this.editingInvoice, items: [...this.editingInvoice.items, newItem] };
        this.isProductPickerOpen = false;
        this.productSearch = '';
    },

    removeItem(index) {
        const items = [...this.editingInvoice.items];
        items.splice(index, 1);
        this.editingInvoice = { ...this.editingInvoice, items };
    },

    updateItem(index, field, value) {
        const items = [...this.editingInvoice.items];
        const item = { ...items[index] };
        const sub = () => item.quantity * item.price;
        if (field === 'productId') {
            const product = this.availableProducts.find((p) => p.id === parseInt(value));
            if (product) {
                item.productId = product.id;
                item.productName = product.name;
                item.price = parseFloat(product.price);
            }
        } else if (field === 'discountType') {
            item.discountType = value;
            if (value === 'percent') item.discountAmount = (sub() * (item.discountPercentage || 0)) / 100;
            else item.discountPercentage = sub() > 0 ? (item.discountAmount / sub()) * 100 : 0;
        } else if (field === 'discountPercentage') {
            item.discountPercentage = value;
            item.discountAmount = (sub() * (value || 0)) / 100;
        } else if (field === 'discountAmount') {
            item.discountAmount = value;
            item.discountPercentage = sub() > 0 ? (value / sub()) * 100 : 0;
        } else {
            item[field] = value;
            if (field === 'quantity' || field === 'price') {
                if (item.discountType === 'percent') item.discountAmount = (sub() * (item.discountPercentage || 0)) / 100;
                else item.discountPercentage = sub() > 0 ? (item.discountAmount / sub()) * 100 : 0;
            }
        }
        items[index] = item;
        this.editingInvoice = { ...this.editingInvoice, items };
    },

    get totals() {
        const inv = this.editingInvoice;
        if (!inv) return { subtotal: 0, discount: 0, total: 0, changeDue: 0, amountDue: 0, newCustomerBalance: 0, totalPaid: 0 };
        const subtotal = Math.round(inv.items.reduce((acc, item) => acc + item.quantity * item.price, 0));
        const discount = Math.round(inv.items.reduce((acc, item) => acc + (item.discountAmount || 0), 0));
        const total = subtotal - discount;
        const oldPaid = Math.round(inv.amountTendered || 0);
        const paymentNow = Math.round(inv.paymentReceived || 0);
        const totalPaid = oldPaid + paymentNow;
        const amountDue = total - totalPaid;
        const balanceChange = (total - Math.round(inv.total || 0)) - paymentNow;
        const newCustomerBalance = Math.round(inv.customerBalance || 0) + balanceChange;
        return { subtotal, discount, total, amountDue, newCustomerBalance, totalPaid };
    },

    async handleSave() {
        const totals = this.totals;
        if (totals.amountDue < 0) {
            toast.error(`Payment exceeds amount due. Maximum additional payment allowed: PKR ${(totals.totalPaid - (this.editingInvoice.paymentReceived || 0) - totals.total) * -1}`);
            toast.error('Total payment cannot exceed the invoice total.');
            return;
        }
        this.saving = true;
        try {
            const inv = this.editingInvoice;
            await api.put(`/admin/labs/invoices/${inv.id}`, {
                customerName: inv.customerName,
                phone: inv.phone,
                amountTendered: totals.totalPaid,
                items: inv.items.map((item) => ({
                    productId: item.productId,
                    quantity: item.quantity,
                    price: item.price,
                    discountPercentage: item.discountType === 'percent' ? item.discountPercentage : undefined,
                    discountAmount: item.discountType === 'amount' ? item.discountAmount : undefined,
                })),
            });
            toast.success('Invoice updated and synchronized');
            this.isEditModalOpen = false;
            this.fetchInvoices();
        } catch (err) {
            toast.error('Failed to update invoice');
        } finally {
            this.saving = false;
        }
    },

    async handleDownload(id) {
        try {
            toast.info(`Downloading digital invoice #${id}...`);
            const response = await api.get('/admin/labs/invoice/download', { params: { orderId: id, showHeader: this.showHeader }, responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `invoice-${id}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.parentNode?.removeChild(link);
            window.URL.revokeObjectURL(url);
        } catch (err) {
            toast.error('Download failed');
        }
    },

    async handlePreview(id) {
        this.previewing = true;
        try {
            toast.info(`Generating preview for invoice #${id}...`);
            const response = await api.get('/admin/labs/invoice/download', { params: { orderId: id, showHeader: this.showHeader }, responseType: 'blob' });
            this.previewUrl = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        } catch (err) {
            toast.error('Preview failed');
        } finally {
            this.previewing = false;
        }
    },

    downloadPreview() {
        const link = document.createElement('a');
        link.href = this.previewUrl;
        link.setAttribute('download', 'Invoice-Preview.pdf');
        document.body.appendChild(link);
        link.click();
        link.remove();
    },

    closePreview() {
        URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = null;
    },
}));
