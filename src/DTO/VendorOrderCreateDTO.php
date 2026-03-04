<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class VendorOrderCreateDTO
{
    #[Assert\NotBlank(message: 'Vendor ID is required')]
    public ?int $vendorId = null;

    #[Assert\NotBlank(message: 'Product ID is required')]
    public ?int $productId = null;

    #[Assert\NotBlank(message: 'Quantity is required')]
    #[Assert\GreaterThan(value: 0, message: 'Quantity must be greater than zero')]
    public ?int $quantity = null;

    public ?string $comment = null;
}