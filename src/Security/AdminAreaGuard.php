<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Server-side version of the redirect useEffects in the React layouts:
 *  - admin/layout.tsx       → every "web_admin_*" route
 *  - super-admin/layout.tsx → every "web_superadmin_*" route
 * The JSON API keeps its own access_control rules.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)] // after the firewall (8)
class AdminAreaGuard
{
    public function __construct(private Security $security)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        if (str_starts_with($route, 'web_superadmin_')) {
            // if (!isAuthenticated || !user?.roles.includes('ROLE_SUPER_ADMIN')) router.push('/login')
            if (!$user instanceof User || !in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
                $event->setResponse(new RedirectResponse('/login'));
            }

            return;
        }

        if (!str_starts_with($route, 'web_admin_')) {
            return;
        }

        $slug = $request->attributes->get('companySlug');

        if (!$user instanceof User) {
            $event->setResponse(new RedirectResponse("/$slug/login"));

            return;
        }

        $access = new AdminAccess($user);
        if (!$access->isAuthorized()) {
            $event->setResponse(new RedirectResponse("/$slug"));
        } elseif (!$access->canVisit($request->getPathInfo())) {
            $event->setResponse(new RedirectResponse("/$slug/admin/dashboard-lab"));
        }
    }
}
