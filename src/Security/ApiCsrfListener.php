<?php

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * /api endpoints now also accept the session cookie (used by fetch() calls from Twig pages).
 * Cookie-authenticated writes must carry the X-CSRF-Token header (see assets/lib/api.js).
 * Bearer-token (JWT) requests are unaffected.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 9)] // before the firewall
class ApiCsrfListener
{
    public function __construct(private CsrfTokenManagerInterface $csrfTokenManager)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()
            || $request->isMethodSafe()
            || !preg_match('#^/(?:[^/]+/)?api(?:/|$)#', $request->getPathInfo())
            || $request->headers->has('Authorization')
            || !$request->hasPreviousSession()) {
            return;
        }

        $token = new CsrfToken('api', (string) $request->headers->get('X-CSRF-Token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $event->setResponse(new JsonResponse(['message' => 'Invalid CSRF token'], 403));
        }
    }
}
