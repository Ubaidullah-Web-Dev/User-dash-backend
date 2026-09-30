<?php

namespace App\Presenter;

use App\Entity\Vendor;

/** Single source of truth for vendor data: used by the JSON API and by the Twig pages. */
class VendorPresenter
{
    public function toListItem(Vendor $vendor): array
    {
        return [
            'id' => $vendor->getId(),
            'name' => $vendor->getName(),
            'email' => $vendor->getEmail(),
            'phone' => $vendor->getPhone(),
            'companyName' => $vendor->getCompanyName(),
            'status' => $vendor->getStatus(),
            'category' => $vendor->getCategory() ? [
                'id' => $vendor->getCategory()->getId(),
                'name' => $vendor->getCategory()->getName(),
            ] : null,
        ];
    }
}
