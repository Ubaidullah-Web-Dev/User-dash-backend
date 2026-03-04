<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class OrderCreateDTO
{
    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(min: 2, minMessage: 'Name must be at least {{ limit }} characters long')]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Address is required')]
    #[Assert\Length(min: 5, minMessage: 'Address must be at least {{ limit }} characters long')]
    public ?string $address = null;

    #[Assert\NotBlank(message: 'Phone is required')]
    #[Assert\Length(max: 20, maxMessage: 'Phone number cannot be longer than {{ limit }} characters')]
    public ?string $phone = null;
}