import './bootstrap.js';
import './styles/app.css';
import * as Turbo from '@hotwired/turbo';
import Alpine from 'alpinejs';
import registerMotion from './lib/motion.js';
import registerUi from './lib/ui.js';

// Full page loads (like Next's hard navigations); Turbo Frames keep working where used.
Turbo.session.drive = false;

window.Alpine = Alpine;
registerMotion(Alpine);
registerUi(Alpine);

// One module per page (the former Next.js route files). Loaded on demand; each registers Alpine.data(...).
const pages = {
    'landing': () => import('./pages/landing.js'),
    'tenant-home': () => import('./pages/tenant-home.js'),
    'login': () => import('./pages/login.js'),
    'register': () => import('./pages/register.js'),
    'forgot-password': () => import('./pages/forgot-password.js'),
    'reset-password': () => import('./pages/reset-password.js'),
    'marketplace-layout': () => import('./pages/marketplace-layout.js'),
    'marketplace': () => import('./pages/marketplace.js'),
    'marketplace-product': () => import('./pages/marketplace-product.js'),
    'post-ad': () => import('./pages/post-ad.js'),
    'cart': () => import('./pages/cart.js'),
    'checkout-address': () => import('./pages/checkout-address.js'),
    'checkout-summary': () => import('./pages/checkout-summary.js'),
    'posts': () => import('./pages/posts.js'),
    'my-ads': () => import('./pages/my-ads.js'),
    'profile-settings': () => import('./pages/profile-settings.js'),
    'admin-vendors': () => import('./pages/admin-vendors.js'),
    'admin-dashboard': () => import('./pages/admin-dashboard.js'),
    'admin-dashboard-lab': () => import('./pages/admin-dashboard-lab.js'),
    'admin-categories': () => import('./pages/admin-categories.js'),
    'admin-category-detail': () => import('./pages/admin-category-detail.js'),
    'admin-customers-lab': () => import('./pages/admin-customers-lab.js'),
    'admin-customer-form': () => import('./pages/admin-customer-form.js'),
    'admin-invoices-lab': () => import('./pages/admin-invoices-lab.js'),
    'admin-lab-expenses': () => import('./pages/admin-lab-expenses.js'),
    'admin-cash-recovery': () => import('./pages/admin-cash-recovery.js'),
    'admin-orders': () => import('./pages/admin-orders.js'),
    'admin-orders-new': () => import('./pages/admin-orders-new.js'),
    'admin-pos': () => import('./pages/admin-pos.js'),
    'admin-products': () => import('./pages/admin-products.js'),
    'admin-reagents': () => import('./pages/admin-reagents.js'),
    'admin-reagent-form': () => import('./pages/admin-reagent-form.js'),
    'admin-reports-lab': () => import('./pages/admin-reports-lab.js'),
    'admin-sales-lab': () => import('./pages/admin-sales-lab.js'),
    'admin-settings': () => import('./pages/admin-settings.js'),
    'admin-stock': () => import('./pages/admin-stock.js'),
    'admin-stock-low': () => import('./pages/admin-stock-low.js'),
    'admin-stock-in': () => import('./pages/admin-stock-in.js'),
    'admin-stock-vendor-new': () => import('./pages/admin-stock-vendor-new.js'),
    'admin-users': () => import('./pages/admin-users.js'),
    'admin-vendor-new': () => import('./pages/admin-vendor-new.js'),
    'super-admin-layout': () => import('./pages/super-admin-layout.js'),
    'super-admin': () => import('./pages/super-admin.js'),
    'super-admin-companies': () => import('./pages/super-admin-companies.js'),
    'super-admin-banner': () => import('./pages/super-admin-banner.js'),
};

(async () => {
    const names = (document.body.dataset.pages || '').split(' ').filter(Boolean);
    await Promise.all(names.map((name) => pages[name] && pages[name]()));
    Alpine.start();

    // Like React 19's hydration, replay load errors of images that failed before Alpine attached
    // their @error handlers (e.g. fallback images), so the fallbacks still apply.
    requestAnimationFrame(() => {
        document.querySelectorAll('img').forEach((img) => {
            if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) img.dispatchEvent(new Event('error'));
        });
    });
})();
