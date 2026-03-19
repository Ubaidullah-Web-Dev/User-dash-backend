<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\TenantContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
class TenantSecurityListener
{
    public function __construct(
        private Security $security,
        private TenantContext $tenantContext
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $companySlug = $request->attributes->get('companySlug');
        
        // If there's no company resolved, or it's a super-admin route, skip
        if (!$companySlug || $request->attributes->get('_route') === 'super_admin') {
            return;
        }

        /** @var User|null $user */
        $user = $this->security->getUser();

        if (!$user) {
            return; // Security firewall will handle unauthorized access
        }

        // Super admins have global access
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            return;
        }

        // For regular admins and users, check if they belong to the current tenant
        $currentCompany = $this->tenantContext->getCurrentCompany();
        
        if ($currentCompany && $user->getCompany() && $user->getCompany()->getId() !== $currentCompany->getId()) {
            throw new AccessDeniedHttpException('Cross-tenant access denied. You do not belong to this node.');
        }
    }
}
