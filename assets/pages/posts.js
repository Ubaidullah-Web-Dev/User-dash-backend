// Port of app/(tenant)/[companySlug]/posts/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { auth } from '../lib/auth.js';
import { router } from '../lib/router.js';

Alpine.data('postsPage', () => ({
    user: auth.user,
    posts: [],
    newTitle: '',
    newContent: '',
    isSubmitting: false,
    editingPost: null,
    editTitle: '',
    editContent: '',

    init() {
        if (!auth.isAuthenticated) {
            router.push('/login');
            return;
        }
        this.fetchPosts();
    },

    async fetchPosts() {
        try {
            const response = await api.get('/posts');
            this.posts = response.data;
        } catch (err) {
            console.error(err);
            toast.error('Access Denied', { description: 'You might not have permission to view posts.' });
        }
    },

    async handleCreate() {
        this.isSubmitting = true;
        try {
            await api.post('/posts', { title: this.newTitle, content: this.newContent });
            toast.success('Post created!');
            this.newTitle = '';
            this.newContent = '';
            this.fetchPosts();
        } catch (err) {
            console.error(err);
            toast.error('Action Denied', { description: "You don't have permission to create posts." });
        } finally {
            this.isSubmitting = false;
        }
    },

    handleEditStart(post) {
        this.editingPost = post;
        this.editTitle = post.title;
        this.editContent = post.content;
    },

    async handleUpdate() {
        if (!this.editingPost) return;
        this.isSubmitting = true;
        try {
            await api.put(`/posts/${this.editingPost.id}`, { title: this.editTitle, content: this.editContent });
            toast.success('Post updated!');
            this.editingPost = null;
            this.fetchPosts();
        } catch (err) {
            console.error(err);
            toast.error('Action Denied', { description: "You don't have permission to edit this post." });
        } finally {
            this.isSubmitting = false;
        }
    },

    async handleDelete(id) {
        try {
            await api.delete(`/posts/${id}`);
            toast.success('Post deleted!');
            this.fetchPosts();
        } catch (err) {
            console.error(err);
            toast.error('Action Denied', { description: "You don't have permission to delete this post." });
        }
    },

    canEdit(post) {
        const roles = this.user?.roles || [];
        return this.user?.email === post.authorEmail || roles.includes('ROLE_ADMIN') || roles.includes('ROLE_SUPER_ADMIN');
    },
    get isSuperAdmin() { return (this.user?.roles || []).includes('ROLE_SUPER_ADMIN'); },
}));
