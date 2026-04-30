<?php

namespace App\EventListener;

use App\Repository\CompanyRepository;
use App\Service\TenantContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 100)]
class TenantResolverListener
{
    public function __construct(
        private CompanyRepository $companyRepository,
        private TenantContext $tenantContext
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();
        $segments = explode('/', trim($pathInfo, '/'));

        if (empty($segments[0])) {
            return;
        }

        $slug = $segments[0];

        if ($slug === 'super-admin') {
            return; 
        }

        if ($slug === 'api') {
            if (isset($segments[1]) && $segments[1] === 'super-admin') {
                return; 
            }
            $slug = 'unique-healthcare-solutions';
        }

        if (in_array($slug, ['_profiler', '_wdt'])) {
            return;
        }

        $company = $this->companyRepository->findOneBy(['slug' => $slug]);

        if (!$company) {
            $company = $this->companyRepository->findOneBy(['slug' => 'unique-healthcare-solutions']);
        }

        if ($company) {
            $this->tenantContext->setCurrentCompany($company);
            $request->attributes->set('company', $company);
            $request->attributes->set('companySlug', $company->getSlug());
        }
    }
}
