<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class JWTCreatedListener
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $payload = $event->getData();
        $content = json_decode($request->getContent(), true);

        if (isset($content['remember_me']) && $content['remember_me'] === true) {
            // Set expiration to 1 year
            $expiration = new \DateTime('+1 year');
            $payload['exp'] = $expiration->getTimestamp();
        }

        $event->setData($payload);
    }
}