// Port of app/(tenant)/[companySlug]/admin/lab-expenses/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { debounce } from '../lib/utils.js';

const EXPENSE_CATEGORIES = ['Utility', 'Equipment', 'Reagents', 'Maintenance', 'Salaries', 'Logistics', 'Marketing', 'Miscellaneous', 'Other'];
const today = () => new Date().toISOString().split('T')[0];
const emptyExpense = () => ({ title: '', description: '', amount: '', category: 'Miscellaneous', expenseDate: today() });

Alpine.data('labExpenses', () => ({
    expenses: [],
    loading: true,
    searchQuery: '',
    startDate: '',
    endDate: '',
    currentPage: 1,
    totalPages: 1,
    totalItems: 0,
    limit: 15,
    showAddModal: false,
    previewUrl: null,
    previewing: null,
    showHeader: true,
    newExpense: emptyExpense(),
    categoryOptions: EXPENSE_CATEGORIES.map((c) => ({ value: c, label: c, class: 'font-bold py-2 text-sm' })),

    init() {
        this.debouncedSearch = debounce((query, start, end) => { this.currentPage = 1; this.fetchExpenses(1, query, start, end); }, 500);
        this.$watch('currentPage', () => this.fetchExpenses(this.currentPage, this.searchQuery, this.startDate, this.endDate));
        this.fetchExpenses(this.currentPage, this.searchQuery, this.startDate, this.endDate);
    },

    async fetchExpenses(page = this.currentPage, search = this.searchQuery, start = this.startDate, end = this.endDate) {
        this.loading = true;
        try {
            const params = { search: search || undefined, page, limit: this.limit };
            if (start) params.startDate = start;
            if (end) params.endDate = end;
            const response = await api.get('/admin/labs/expenses', { params });
            this.expenses = response.data.data;
            this.totalPages = response.data.pages;
            this.totalItems = response.data.total;
        } catch (err) {
            console.error(err);
            toast.error('Failed to load expenses');
        } finally {
            this.loading = false;
        }
    },

    onFiltersChanged() { this.debouncedSearch(this.searchQuery, this.startDate, this.endDate); },

    resetFilters() {
        this.searchQuery = '';
        this.startDate = '';
        this.endDate = '';
        this.currentPage = 1;
        this.fetchExpenses(1, '', '', '');
    },

    async handleDelete(id) {
        if (!confirm('Are you sure you want to delete this expense? This action cannot be undone.')) return;
        try {
            await api.delete(`/admin/labs/expenses/${id}`);
            this.fetchExpenses(this.currentPage, this.searchQuery, this.startDate, this.endDate);
            toast.success('Expense deleted successfully');
        } catch (err) {
            console.error(err);
            toast.error('Failed to delete expense');
        }
    },

    async handleCreateExpense() {
        try {
            await api.post('/admin/labs/expenses', { ...this.newExpense, amount: Math.round(parseFloat(this.newExpense.amount)) });
            toast.success('Expense recorded successfully');
            this.showAddModal = false;
            this.newExpense = emptyExpense();
            this.fetchExpenses(1, this.searchQuery, this.startDate, this.endDate);
        } catch (err) {
            console.error(err);
            toast.error(err?.response?.data?.message || (err instanceof Error ? err.message : 'Failed to create expense'));
        }
    },

    async handleDownload(id) {
        try {
            toast.info(`Downloading digital expense receipt #${id}...`);
            const response = await api.get(`/admin/labs/invoice/expense/${id}`, { params: { showHeader: this.showHeader }, responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Expense-${id}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.parentNode?.removeChild(link);
            window.URL.revokeObjectURL(url);
        } catch (err) {
            toast.error('Download failed');
        }
    },

    async handlePreview(id) {
        this.previewing = id;
        try {
            toast.info(`Generating preview for expense #${id}...`);
            const response = await api.get(`/admin/labs/invoice/expense/${id}`, { params: { showHeader: this.showHeader }, responseType: 'blob' });
            this.previewUrl = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        } catch (err) {
            toast.error('Preview failed');
        } finally {
            this.previewing = null;
        }
    },

    downloadPreview() {
        const link = document.createElement('a');
        link.href = this.previewUrl;
        link.setAttribute('download', 'Expense-Receipt.pdf');
        document.body.appendChild(link);
        link.click();
        link.remove();
    },

    closePreview() {
        URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = null;
    },
}));
