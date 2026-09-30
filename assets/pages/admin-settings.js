// Port of app/(tenant)/[companySlug]/admin/settings/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';

Alpine.data('adminSettings', () => ({
    isBackingUp: false,

    async handleBackup() {
        try {
            this.isBackingUp = true;
            toast.info('Initializing database backup...');
            const response = await api.get('/admin/database/backup', { responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `db_backup_${new Date().toISOString().slice(0, 10)}.sql`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            toast.success('Database backup downloaded successfully');
        } catch (error) {
            console.error('Backup failed', error);
            toast.error('Failed to generate database backup');
        } finally {
            this.isBackingUp = false;
        }
    },
}));
