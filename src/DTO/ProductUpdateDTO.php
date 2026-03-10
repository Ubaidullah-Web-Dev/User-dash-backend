<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ProductUpdateDTO
{
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    public ?string $description = null;

    public ?float $price = null;

    public ?int $stock = null;

    public ?bool $isRecommended = null;

    public ?string $companyName = null;

    public ?string $packSize = null;

    public ?float $purchasePrice = null;

    public ?string $expiryDate = null;

    public ?string $batchNumber = null;

    public ?int $minimumStock = null;

    public ?int $categoryId = null;

    public ?string $unit = null;
}