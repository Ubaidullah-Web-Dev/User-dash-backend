<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ProductCreateDTO
{
    #[Assert\NotBlank(message: 'Product name is required')]
    public ?string $name = null;

    public ?string $description = null;

    #[Assert\NotBlank(message: 'Price is required')]
    #[Assert\PositiveOrZero(message: 'Price must be positive or zero')]
    public ?float $price = null;

    #[Assert\NotBlank(message: 'Stock is required')]
    #[Assert\Type(type: 'integer', message: 'Stock must be an integer')]
    public ?int $stock = null;

    #[Assert\Regex(pattern: '/^[a-zA-Z0-9 ]{1,20}$/', message: 'Invalid unit format. Use max 20 alphanumeric characters.')]
    public ?string $unit = null;

    public ?string $companyName = null;

    public ?string $packSize = null;

    public ?float $purchasePrice = null;

    public ?string $expiryDate = null; // Y-m-d format

    public ?string $batchNumber = null;

    public ?int $minimumStock = 0;

    #[Assert\NotBlank(message: 'Category is required')]
    public ?int $category_id = null;

    public ?bool $isRecommended = false;

    /**
     * @var string[]|null
     */
    #[Assert\All([
        new Assert\Url(message: 'Each image must be a valid URL')
    ])]
    public ?array $images = [];
}