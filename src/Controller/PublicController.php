<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use App\Service\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public')]
class PublicController extends AbstractController
{
    public function __construct(
        private TenantContext $tenantContext,
        private CompanyRepository $companyRepository
    ) {}

    #[Route('/company/{slug}', name: 'public_company_info', methods: ['GET'])]
    public function getCompanyInfo(string $slug): JsonResponse
    {
        $company = $this->companyRepository->findOneBy(['slug' => $slug]);

        if (!$company) {
            return $this->json(['message' => 'Company not found'], 404);
        }

        return $this->json([
            'id' => $company->getId(),
            'name' => $company->getName(),
            'slug' => $company->getSlug(),
            'settings' => $company->getSettingsJson(),
        ]);
    }
}
