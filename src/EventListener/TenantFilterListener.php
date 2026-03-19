<?php

namespace App\EventListener;

use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 50)]
class TenantFilterListener
{
    public function __construct(
        private EntityManagerInterface $em,
        private TenantContext $tenantContext
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $company = $this->tenantContext->getCurrentCompany();
        
        if ($company) {
            $filter = $this->em->getFilters()->enable('tenant_filter');
            $filter->setParameter('company_id', $company->getId());
        } else {
            // If no company is set (e.g. super-admin or public homepage), we might want to disable the filter
            // OR we want to default to company 1? 
            // The user said: "Treat the existing website as the default company called Unique Healthcare Solutions".
            // If they access it via old URLs (without slug), we should default to company 1.
            
            // For now, let's keep it disabled if no company is resolved, 
            // but we might want to default to 1 if it's not a super-admin route.
        }
    }
}
