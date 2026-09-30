// Port of app/(tenant)/[companySlug]/marketplace/[slug]/page.tsx
import Alpine from 'alpinejs';
import api, { getImageUrl } from '../lib/api.js';
import { toast } from '../lib/toast.js';
import { router, params } from '../lib/router.js';

Alpine.data('productPage', () => ({
    product: null,
    loading: true,
    activeImage: 0,
    quantity: 1,
    inCartQuantity: 0,
    addingToCart: false,
    getImageUrl,

    async init() {
        try {
            const [productRes, cartRes] = await Promise.all([
                api.get(`/products/${params().slug}`),
                api.get('/cart').catch(() => ({ data: [] })),
            ]);
            const productData = productRes.data;
            this.product = productData;
            const existingItem = cartRes.data.find((item) => item.productId === productData.id && !item.isSavedForLater);
            this.inCartQuantity = existingItem ? existingItem.quantity : 0;
        } catch (err) {
            console.error('Failed to fetch data', err);
            toast.error('Product not found');
            router.push(`/${params().companySlug}/marketplace`);
        } finally {
            this.loading = false;
        }
    },

    async handleAddToCart(isSavedForLater = false) {
        this.addingToCart = true;
        try {
            await api.post('/cart', {
                product_id: this.product?.id,
                quantity: this.quantity,
                is_saved_for_later: isSavedForLater,
            });
            toast.success(isSavedForLater ? 'Saved for later' : 'Added to cart', {
                description: `${this.product?.name} has been updated in your cart.`,
            });
            if (!isSavedForLater) {
                this.inCartQuantity += this.quantity;
                this.quantity = 1;
            }
            // fetchCartCount(): no-op — CartProvider was never mounted in the Next app
        } catch (error) {
            console.error(error);
            if (error.response?.status === 401) {
                toast.error('Authentication required', { description: 'Please login to manage your cart.' });
                router.push(`/login?redirectTo=${encodeURIComponent(window.location.pathname)}`);
            } else {
                toast.error('Action failed', { description: error.response?.data?.message || 'An unexpected error occurred.' });
            }
        } finally {
            this.addingToCart = false;
        }
    },

    get remaining() { return this.product.stock - this.inCartQuantity; },
    nextImage() { this.activeImage = (this.activeImage + 1) % (this.product.images.length || 1); },
    prevImage() { this.activeImage = (this.activeImage - 1 + (this.product.images.length || 1)) % (this.product.images.length || 1); },
    back() { router.back(); },
}));
