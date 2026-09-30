<?php

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/** AuthContext.logout(): router.push(slug ? `/${slug}/login` : '/login') */
#[AsEventListener]
class LogoutRedirectSubscriber
{
    public function __invoke(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $slug = $request->request->get('companySlug') ?: $request->attributes->get('companySlug');
        if ($slug && !preg_match('/^[A-Za-z0-9_-]+$/', (string) $slug)) {
            $slug = null;
        }
        $event->setResponse(new RedirectResponse($slug ? "/$slug/login" : '/login'));
    }
}
