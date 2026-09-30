// Port of app/(tenant)/[companySlug]/admin/reports-lab/page.tsx
import Alpine from 'alpinejs';
import api from '../lib/api.js';
import { toast } from '../lib/toast.js';

const today = () => new Date().toISOString().split('T')[0];

Alpine.data('reportsLab', () => ({
    data: null,
    loading: true,
    previewUrl: null,
    previewing: false,
    showHeader: true,
    startDate: today(),
    endDate: today(),
    generatedAt: new Date().toLocaleTimeString(),

    async init() {
        try {
            const response = await api.get('/admin/labs/reports');
            this.data = response.data;
        } catch (err) {
            console.error('Failed to load reports', err);
            toast.error('Failed to sync report data');
        } finally {
            this.loading = false;
        }
    },

    summaryEndpoint(type, date) {
        let endpoint = `/admin/labs/invoice/summary?type=${type}`;
        if (type === 'custom') {
            endpoint += `&startDate=${this.startDate}&endDate=${this.endDate}`;
        } else if (date) {
            endpoint += `&date=${date}`;
        } else {
            const now = new Date();
            endpoint += `&year=${now.getFullYear()}&month=${now.getMonth() + 1}`;
        }
        return endpoint + `&showHeader=${this.showHeader}`;
    },

    async downloadSummary(type, date) {
        try {
            const endpoint = this.summaryEndpoint(type, date);
            toast.info(`Compiling ${type} intelligence report...`);
            const response = await api.get(endpoint, { responseType: 'blob' });
            const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `Lab-Summary-${type}-${date || new Date().toISOString().split('T')[0]}.pdf`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
            toast.success(`${type.charAt(0).toUpperCase() + type.slice(1)} report downloaded`);
        } catch (err) {
            toast.error('Download failed');
        }
    },

    async previewSummary(type, date) {
        this.previewing = true;
        try {
            const endpoint = this.summaryEndpoint(type, date);
            toast.info(`Generating ${type} preview...`);
            const response = await api.get(endpoint, { responseType: 'blob' });
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
        link.setAttribute('download', 'Summary-Preview.pdf');
        document.body.appendChild(link);
        link.click();
        link.remove();
    },

    closePreview() {
        URL.revokeObjectURL(this.previewUrl);
        this.previewUrl = null;
    },
}));
