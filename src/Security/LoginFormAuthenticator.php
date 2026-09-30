<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Session login for the Twig pages. Replaces POST /api/login + localStorage JWT + AuthContext.login().
 *
 * The login pages post JSON (like the old axios call) and get JSON back, so a failed login shows the
 * error toast without reloading the page; a plain form post is also supported.
 */
class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && in_array($request->attributes->get('_route'), ['web_login', 'web_tenant_login'], true);
    }

    public function authenticate(Request $request): Passport
    {
        $data = $this->isJson($request) ? (json_decode($request->getContent(), true) ?: []) : $request->request->all();
        $email = (string) ($data['email'] ?? '');
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        $rememberMe = new RememberMeBadge();
        if (!empty($data['remember_me']) || !empty($data['_remember_me'])) {
            $rememberMe->enable();
        }
        $request->attributes->set('_login_redirect_to', $data['redirectTo'] ?? null);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials((string) ($data['password'] ?? '')),
            [
                new CsrfTokenBadge('authenticate', (string) ($data['_csrf_token'] ?? '')),
                $rememberMe,
            ],
        );
    }

    /** Same redirect rules as AuthContext.login() */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $url = $this->successUrl($request, $token, $firewallName);

        return $this->isJson($request) ? new JsonResponse(['redirect' => $url]) : new RedirectResponse($url);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($this->isJson($request)) {
            return new JsonResponse(['message' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        return parent::onAuthenticationFailure($request, $exception);
    }

    private function successUrl(Request $request, TokenInterface $token, string $firewallName): string
    {
        $redirectTo = $request->attributes->get('_login_redirect_to') ?: $this->getTargetPath($request->getSession(), $firewallName);
        // Only same-site paths (no open redirect via ?redirectTo=https://...)
        if ($redirectTo && str_starts_with($redirectTo, '/') && !str_starts_with($redirectTo, '//')) {
            return $redirectTo;
        }

        $roles = $token->getRoleNames();
        $has = static fn (string ...$r) => (bool) array_intersect($r, $roles);
        $companySlug = 'web_tenant_login' === $request->attributes->get('_route')
            ? $request->attributes->get('companySlug')
            : null;

        if ($companySlug) {
            return $has('ROLE_ADMIN', 'ROLE_SUPER_ADMIN', 'ROLE_ARCHITECTURAL', 'ROLE_MODERATOR', 'ROLE_EMPLOYEE')
                ? "/$companySlug/admin/dashboard-lab"
                : "/$companySlug";
        }
        if ($has('ROLE_SUPER_ADMIN')) {
            return '/super-admin';
        }
        if ($has('ROLE_ADMIN')) {
            return '/unique-healthcare-solutions/admin/dashboard';
        }

        return '/';
    }

    private function isJson(Request $request): bool
    {
        return str_contains((string) $request->headers->get('Content-Type'), 'json');
    }

    protected function getLoginUrl(Request $request): string
    {
        $slug = $request->attributes->get('companySlug');

        return $slug
            ? $this->urlGenerator->generate('web_tenant_login', ['companySlug' => $slug])
            : $this->urlGenerator->generate('web_login');
    }
}
