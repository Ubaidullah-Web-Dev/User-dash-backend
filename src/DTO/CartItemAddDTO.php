<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CartItemAddDTO
{
    #[Assert\NotBlank]
    public ?int $product_id = null;

    #[Assert\GreaterThan(0)]
    public int $quantity = 1;

    public bool $is_saved_for_later = false;
}