<?php

namespace App\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class TenantFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (!$targetEntity->hasAssociation('company')) {
            return '';
        }

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
