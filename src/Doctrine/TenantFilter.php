<?php

namespace App\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        // Check if the entity has a company relation or company_id column
        // We can check if it implements a specific interface or just check by entity name for now
        // A more robust way is to check the class metadata for the 'company' association.
        
        if (!$targetEntity->hasAssociation('company')) {
            return '';
        }

        // Allow loading users globally to support cross-tenant authentication check
        if ($targetEntity->reflClass->name === \App\Entity\User::class) {
            return '';
        }

        try {
            $companyId = $this->getParameter('company_id');
        } catch (\InvalidArgumentException) {
            return '';
        }

        if (!$companyId) {
            return '';
        }

        return sprintf('%s.company_id = %s', $targetTableAlias, $companyId);
    }
}
