<?php

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Every former Next.js route (every page.tsx under frontend/app) mapped to its Twig template.
 * Pages load their data from the same JSON API the React pages used; access rules
 * that lived in the React layouts are enforced by App\Security\AdminAreaGuard.
 */
class PageController extends AbstractController
{
    private const SLUG = '%tenant_slug_requirement%';

    // ---- Global -------------------------------------------------------------------------

    #[Route('/', name: 'web_landing', methods: ['GET'])]
    public function landing(): Response
    {
        return $this->render('pages/landing.html.twig');
    }

    #[Route('/login', name: 'web_login', methods: ['GET', 'POST'])]
    public function login(): Response
    {
        return $this->render('security/login.html.twig');
    }

    #[Route('/logout', name: 'web_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the logout key on the main firewall.');
    }

    #[Route('/super-admin', name: 'web_superadmin_index', methods: ['GET'])]
    public function superAdmin(): Response
    {
        return $this->render('super_admin/index.html.twig');
    }

    #[Route('/super-admin/companies', name: 'web_superadmin_companies', methods: ['GET'])]
    public function superAdminCompanies(): Response
    {
        return $this->render('super_admin/companies.html.twig');
    }

    #[Route('/super-admin/banner', name: 'web_superadmin_banner', methods: ['GET'])]
    public function superAdminBanner(): Response
    {
        return $this->render('super_admin/banner.html.twig');
    }

    // ---- Tenant storefront ----------------------------------------------------------------

    #[Route('/{companySlug}', name: 'web_tenant_home', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function tenantHome(string $companySlug): Response
    {
        return $this->render('pages/tenant_home.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/login', name: 'web_tenant_login', requirements: ['companySlug' => self::SLUG], methods: ['GET', 'POST'])]
    public function tenantLogin(string $companySlug): Response
    {
        return $this->render('security/tenant_login.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/register', name: 'web_tenant_register', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function register(string $companySlug): Response
    {
        return $this->render('security/register.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/forgot-password', name: 'web_tenant_forgot_password', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function forgotPassword(string $companySlug): Response
    {
        return $this->render('security/forgot_password.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/reset-password', name: 'web_tenant_reset_password', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function resetPassword(string $companySlug): Response
    {
        return $this->render('security/reset_password.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/marketplace', name: 'web_marketplace', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function marketplace(string $companySlug): Response
    {
        return $this->render('marketplace/index.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/marketplace/post-ad', name: 'web_marketplace_post_ad', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function postAd(string $companySlug): Response
    {
        return $this->render('marketplace/post_ad.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/marketplace/{slug}', name: 'web_marketplace_product', requirements: ['companySlug' => self::SLUG], methods: ['GET'], priority: -1)]
    public function product(string $companySlug, string $slug): Response
    {
        return $this->render('marketplace/product.html.twig', ['companySlug' => $companySlug, 'slug' => $slug]);
    }

    #[Route('/{companySlug}/cart', name: 'web_cart', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function cart(string $companySlug): Response
    {
        return $this->render('shop/cart.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/checkout/address', name: 'web_checkout_address', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function checkoutAddress(string $companySlug): Response
    {
        return $this->render('shop/checkout_address.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/checkout/summary/{id}', name: 'web_checkout_summary', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function checkoutSummary(string $companySlug, string $id): Response
    {
        return $this->render('shop/checkout_summary.html.twig', ['companySlug' => $companySlug, 'id' => $id]);
    }

    #[Route('/{companySlug}/posts', name: 'web_posts', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function posts(string $companySlug): Response
    {
        return $this->render('shop/posts.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/profile/my-ads', name: 'web_profile_my_ads', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function myAds(string $companySlug): Response
    {
        return $this->render('shop/my_ads.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/profile/settings', name: 'web_profile_settings', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function profileSettings(string $companySlug): Response
    {
        return $this->render('shop/profile_settings.html.twig', ['companySlug' => $companySlug]);
    }

    // ---- Tenant admin (all guarded by AdminAreaGuard via the "web_admin_" route prefix) ----

    #[Route('/{companySlug}/admin', name: 'web_admin_index', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function adminIndex(string $companySlug): RedirectResponse
    {
        // admin/page.tsx: router.replace(`/${companySlug}/admin/dashboard-lab`)
        return $this->redirect("/$companySlug/admin/dashboard-lab");
    }

    #[Route('/{companySlug}/admin/{page}', name: 'web_admin_page', requirements: ['companySlug' => self::SLUG, 'page' => 'vendors|dashboard|dashboard-lab|customers|categories|customers-lab|invoices-lab|lab-expenses|cash-recovery|orders|pos|products|reagents|reports-lab|sales-lab|settings|stock|users'], methods: ['GET'])]
    public function adminPage(string $companySlug, string $page): Response
    {
        return $this->render('admin/'.str_replace('-', '_', $page).'.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/admin/categories/{id}', name: 'web_admin_category_detail', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function adminCategoryDetail(string $companySlug, string $id): Response
    {
        return $this->render('admin/category_detail.html.twig', ['companySlug' => $companySlug, 'id' => $id]);
    }

    #[Route('/{companySlug}/admin/customers-lab/new', name: 'web_admin_customer_new', requirements: ['companySlug' => self::SLUG], methods: ['GET'], priority: 1)]
    public function adminCustomerNew(string $companySlug): Response
    {
        return $this->render('admin/customer_form.html.twig', ['companySlug' => $companySlug, 'id' => null]);
    }

    #[Route('/{companySlug}/admin/customers-lab/{id}/edit', name: 'web_admin_customer_edit', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function adminCustomerEdit(string $companySlug, string $id): Response
    {
        return $this->render('admin/customer_form.html.twig', ['companySlug' => $companySlug, 'id' => $id]);
    }

    #[Route('/{companySlug}/admin/orders/new', name: 'web_admin_orders_new', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function adminOrdersNew(string $companySlug): Response
    {
        return $this->render('admin/orders_new.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/admin/reagents/new', name: 'web_admin_reagent_new', requirements: ['companySlug' => self::SLUG], methods: ['GET'], priority: 1)]
    public function adminReagentNew(string $companySlug): Response
    {
        return $this->render('admin/reagent_form.html.twig', ['companySlug' => $companySlug, 'id' => null]);
    }

    #[Route('/{companySlug}/admin/reagents/{id}/edit', name: 'web_admin_reagent_edit', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function adminReagentEdit(string $companySlug, string $id): Response
    {
        return $this->render('admin/reagent_form.html.twig', ['companySlug' => $companySlug, 'id' => $id]);
    }

    #[Route('/{companySlug}/admin/stock/low', name: 'web_admin_stock_low', requirements: ['companySlug' => self::SLUG], methods: ['GET'], priority: 1)]
    public function adminStockLow(string $companySlug): Response
    {
        return $this->render('admin/stock_low.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/admin/stock/new', name: 'web_admin_stock_new', requirements: ['companySlug' => self::SLUG], methods: ['GET'], priority: 1)]
    public function adminStockNew(string $companySlug): Response
    {
        return $this->render('admin/stock_in.html.twig', ['companySlug' => $companySlug, 'id' => null]);
    }

    #[Route('/{companySlug}/admin/stock/{id}/in', name: 'web_admin_stock_in', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function adminStockIn(string $companySlug, string $id): Response
    {
        return $this->render('admin/stock_in.html.twig', ['companySlug' => $companySlug, 'id' => $id]);
    }

    #[Route('/{companySlug}/admin/stock/vendors/new', name: 'web_admin_stock_vendor_new', requirements: ['companySlug' => self::SLUG], methods: ['GET'])]
    public function adminStockVendorNew(string $companySlug): Response
    {
        return $this->render('admin/stock_vendor_new.html.twig', ['companySlug' => $companySlug]);
    }

    #[Route('/{companySlug}/admin/vendors/new', name: 'web_admin_vendor_new', requirements: ['companySlug' => self::SLUG], methods: ['GET'], priority: 1)]
    public function adminVendorNew(string $companySlug): Response
    {
        return $this->render('admin/vendor_new.html.twig', ['companySlug' => $companySlug]);
    }
}
