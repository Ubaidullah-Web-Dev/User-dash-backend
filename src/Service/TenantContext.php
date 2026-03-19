<?php

namespace App\Service;

use App\Entity\Company;

class TenantContext
{
    private ?Company $currentCompany = null;

    public function setCurrentCompany(Company $company): void
    {
        $this->currentCompany = $company;
    }

    public function getCurrentCompany(): ?Company
    {
        return $this->currentCompany;
    }

    public function getCurrentCompanyId(): ?int
    {
        return $this->currentCompany?->getId();
    }
}
