<?php

namespace App\Twig;

use App\Entity\User;
use App\Security\AdminAccess;
use App\Service\TenantContext;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(private TenantContext $tenantContext, private Security $security)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tenant_branding', $this->tenantBranding(...)),
            new TwigFunction('admin_access', $this->adminAccess(...)),
        ];
    }

    /**
     * Replaces the GET /public/company/{slug} fetch in admin/layout.tsx:
     * logo falls back to /images/logo.png, banner shown when show_alphasoft_banner === '1'.
     */
    public function tenantBranding(string $companySlug): array
    {
        $company = $this->tenantContext->getCurrentCompany();
        $settings = ($company && $company->getSlug() === $companySlug ? $company->getSettingsJson() : null) ?? [];

        return [
            'logoPath' => !empty($settings['logo']) ? "/tenants/$companySlug/images/{$settings['logo']}" : '/images/logo.png',
            'showBanner' => ($settings['show_alphasoft_banner'] ?? null) === '1',
        ];
    }

    public function adminAccess(): AdminAccess
    {
        $user = $this->security->getUser();

        return new AdminAccess($user instanceof User ? $user : null);
    }
}
