<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class VendorCreateDTO
{
    #[Assert\NotBlank(message: 'Vendor name is required')]
    public ?string $name = null;

    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'The email "{{ value }}" is not a valid email.')]
    public ?string $email = null;

    public ?string $phone = null;

    public ?string $companyName = null;

    public ?string $address = null;

    #[Assert\Choice(choices: ['active', 'inactive'])]
    public ?string $status = 'active';

    #[Assert\NotBlank(message: 'Category is required')]
    public ?int $categoryId = null;
}
