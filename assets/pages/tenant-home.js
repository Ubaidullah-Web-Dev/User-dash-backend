// Port of app/(tenant)/[companySlug]/page.tsx
import Alpine from 'alpinejs';
import AOS from 'aos';
import 'aos/dist/aos.css';
import api from '../lib/api.js';
import { auth } from '../lib/auth.js';
import { params } from '../lib/router.js';

Alpine.data('tenantHome', () => ({
    companySlug: params().companySlug,
    companyInfo: null,
    activePdf: null,
    isMenuOpen: false,
    heroImageIndex: 0,
    heroImages: [],
    pdfDocs: [],
    logoPath: '/images/logo.png',
    isProfileOpen: false,
    showBanner: false,
    isCardVisible: false,
    user: auth.user,
    isAuthenticated: auth.isAuthenticated,
    timer: null,

    init() {
        const observer = new IntersectionObserver(([entry]) => {
            this.isCardVisible = entry.isIntersecting;
        }, { threshold: 0.2 });
        if (this.$refs.card) observer.observe(this.$refs.card);

        this.fetchCompanyData();

        AOS.init({
            duration: 600,
            once: false,
            mirror: true,
            easing: 'ease-in-out-cubic',
            offset: 50,
        });

        this.$watch('heroImages', (images) => {
            clearInterval(this.timer);
            if (images.length === 0) return;
            this.timer = setInterval(() => {
                this.heroImageIndex = (this.heroImageIndex + 1) % this.heroImages.length;
            }, 5000);
        });
    },

    async fetchCompanyData() {
        try {
            const companyResponse = await api.get(`/public/company/${this.companySlug}`);
            const settings = companyResponse.data.settings || {};

            this.companyInfo = { name: companyResponse.data.name, slug: companyResponse.data.slug };

            if (settings.hero_images && Array.isArray(settings.hero_images)) {
                this.heroImages = settings.hero_images.map((img) => `/tenants/${this.companySlug}/images/${img}`);
            } else {
                this.heroImages = ['/images/cbc-analyzer.jpg', '/images/chemistry-Analyzer.jpg', '/images/hba1c-analyzer1.jpg'];
            }

            if (settings.resources && Array.isArray(settings.resources)) {
                this.pdfDocs = settings.resources.map((doc) => ({ ...doc, file: `/tenants/${this.companySlug}/pdfs/${doc.file}` }));
            } else {
                this.pdfDocs = [];
            }

            if (settings.logo) {
                this.logoPath = `/tenants/${this.companySlug}/images/${settings.logo}`;
            }

            this.showBanner = settings.show_alphasoft_banner === '1';
        } catch (error) {
            console.error('Failed to fetch data', error);
        }
    },

    nextHero() { this.heroImageIndex = (this.heroImageIndex + 1) % this.heroImages.length; },
    prevHero() { this.heroImageIndex = (this.heroImageIndex - 1 + this.heroImages.length) % this.heroImages.length; },

    heroFallback(e, i) {
        // Swap in the fallback only once: if the fallback fails too, React kept re-setting it (endless requests).
        if (e.target.dataset.fallback) return;
        e.target.dataset.fallback = '1';
        const customFallbacks = [
            'https://images.unsplash.com/photo-1579684385127-1ef15d508118?auto=format&fit=crop&q=80&w=1200',
            'https://images.unsplash.com/photo-1581093458791-9f3c3900df4b?auto=format&fit=crop&q=80&w=1200',
            'https://images.unsplash.com/photo-1532094349884-543bc11b234d?auto=format&fit=crop&q=80&w=1200',
        ];
        e.target.src = customFallbacks[i] || customFallbacks[0];
    },

    teamFallback(e, i) {
        if (e.target.dataset.fallback) return;
        e.target.dataset.fallback = '1';
        const fallbacks = [
            'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=1000',
            'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=800',
            'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=800',
        ];
        e.target.src = fallbacks[i];
    },

    logout() { auth.logout(); },
}));
