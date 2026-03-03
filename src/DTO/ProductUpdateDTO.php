<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ProductUpdateDTO
{
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    public ?string $description = null;

    public ?string $price = null;

    public ?int $stock = null;

    public ?bool $isRecommended = null;
}