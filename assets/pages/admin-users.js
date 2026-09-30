// Port of app/(tenant)/[companySlug]/admin/users/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { debounce } from '../lib/utils.js';

const ROLE_OPTIONS = [
    { value: 'ROLE_USER', label: 'USER / STAFF', class: 'text-[9px] uppercase py-2' },
    { value: 'ROLE_EMPLOYEE', label: 'LAB EMPLOYEE', class: 'text-[9px] uppercase py-2 text-primary' },
    { value: 'ROLE_MODERATOR', label: 'MODERATOR', class: 'text-[9px] uppercase py-2' },
    { value: 'ROLE_ADMIN', label: 'ADMINISTRATOR', class: 'text-[9px] uppercase py-2 text-primary' },
    { value: 'ROLE_ARCHITECTURAL', label: 'ARCHITECTURAL', class: 'text-[9px] uppercase py-2 text-cyan-500' },
];

Alpine.data('adminUsers', () => ({
    users: [],
    loading: true,
    searchQuery: '',
    roleFilter: 'ALL',
    updating: null,
    currentPage: 1,
    totalPages: 1,
    totalUsers: 0,
    deletingIds: [],
    isCreateModalOpen: false,
    isCreating: false,
    formData: { name: '', email: '', password: '', role: 'ROLE_USER' },
    roleOptions: ROLE_OPTIONS,
    filterOptions: [
        { value: 'ALL', label: 'ALL ENTITIES', class: 'font-semibold text-[9px] uppercase py-2' },
        { value: 'ROLE_USER', label: 'STAFF / USER', class: 'font-semibold text-[9px] uppercase py-2' },
        { value: 'ROLE_EMPLOYEE', label: 'LAB EMPLOYEE', class: 'font-semibold text-[9px] uppercase py-2 text-primary' },
        { value: 'ROLE_MODERATOR', label: 'MODERATOR', class: 'font-semibold text-[9px] uppercase py-2' },
        { value: 'ROLE_ADMIN', label: 'ADMINISTRATOR', class: 'font-semibold text-[9px] uppercase py-2' },
        { value: 'ROLE_ARCHITECTURAL', label: 'ARCHITECTURAL', class: 'font-semibold text-[9px] uppercase py-2 text-cyan-500' },
    ],
    createRoleOptions: [
        { value: 'ROLE_USER', label: 'STAFF / USER', class: 'font-semibold text-[10px] uppercase py-2' },
        { value: 'ROLE_EMPLOYEE', label: 'LAB EMPLOYEE', class: 'font-semibold text-[10px] uppercase py-2 text-primary' },
        { value: 'ROLE_MODERATOR', label: 'MODERATOR', class: 'font-semibold text-[10px] uppercase py-2' },
        { value: 'ROLE_ADMIN', label: 'ADMINISTRATOR', class: 'font-semibold text-[10px] uppercase py-2' },
        { value: 'ROLE_ARCHITECTURAL', label: 'ARCHITECTURAL', class: 'font-semibold text-[10px] uppercase py-2 text-cyan-500' },
    ],

    init() {
        this.debouncedSearch = debounce((query, role) => this.fetchUsers(1, query, role), 500);
        this.fetchUsers(1, '', 'ALL');
    },

    async fetchUsers(page, search, role) {
        this.loading = true;
        try {
            const response = await api.get('/admin/users', { params: { page, limit: 8, search, role: role === 'ALL' ? '' : role } });
            this.users = response.data.data;
            this.totalPages = response.data.pages;
            this.totalUsers = response.data.total;
            this.currentPage = response.data.page;
        } catch (err) {
            console.error(err);
            toast.error('Failed to sync user database');
        } finally {
            this.loading = false;
        }
    },

    handleSearchChange() { this.debouncedSearch(this.searchQuery, this.roleFilter); },
    handleRoleFilterChange(role) { this.fetchUsers(1, this.searchQuery, role); },
    handlePageChange(newPage) {
        if (newPage >= 1 && newPage <= this.totalPages) this.fetchUsers(newPage, this.searchQuery, this.roleFilter);
    },

    defaultRole(u) {
        return u.roles.includes('ROLE_ADMIN') ? 'ROLE_ADMIN'
            : u.roles.includes('ROLE_ARCHITECTURAL') ? 'ROLE_ARCHITECTURAL'
                : u.roles.includes('ROLE_MODERATOR') ? 'ROLE_MODERATOR'
                    : u.roles.includes('ROLE_EMPLOYEE') ? 'ROLE_EMPLOYEE' : 'ROLE_USER';
    },

    async handleRoleUpdate(userId, newRole) {
        try {
            this.updating = userId;
            const response = await api.patch(`/admin/users/${userId}/role`, { roles: [newRole] });
            this.users = this.users.map((u) => (u.id === userId ? { ...u, roles: response.data.user.roles } : u));
            toast.success('Security clearance updated successfully');
        } catch (err) {
            console.error(err);
            toast.error('Protocol elevation failed');
        } finally {
            this.updating = null;
        }
    },

    async handleDeleteUser(userId) {
        if (!window.confirm('ARE YOU SURE YOU WANT TO PERMANENTLY DELETE THIS USER? THIS ACTION CANNOT BE UNDONE.')) return;
        try {
            this.deletingIds = [...this.deletingIds, userId];
            await api.delete(`/admin/users/${userId}`);
            this.users = this.users.filter((u) => u.id !== userId);
            this.totalUsers -= 1;
            toast.success('User database entry purged successfully');
        } catch (err) {
            console.error(err);
            toast.error('Deletion protocol failed');
        } finally {
            this.deletingIds = this.deletingIds.filter((id) => id !== userId);
        }
    },

    async handleCreateUser() {
        this.isCreating = true;
        try {
            await api.post('/admin/users', { ...this.formData, roles: [this.formData.role] });
            toast.success('User identity initialized successfully');
            this.isCreateModalOpen = false;
            this.formData = { name: '', email: '', password: '', role: 'ROLE_USER' };
            this.fetchUsers(this.currentPage, this.searchQuery, this.roleFilter);
        } catch (err) {
            console.error(err);
            toast.error(err.response?.data?.message || 'User initialization failed');
        } finally {
            this.isCreating = false;
        }
    },

    get visibleUsers() { return this.users.filter((u) => !u.roles.includes('ROLE_SUPER_ADMIN')); },
    // [...Array(totalPages)].map(...).slice(max(0, current - 2), min(totalPages, current + 1))
    get pageWindow() {
        const all = Array.from({ length: this.totalPages }, (_, i) => i + 1);
        return all.slice(Math.max(0, this.currentPage - 2), Math.min(this.totalPages, this.currentPage + 1));
    },
}));
