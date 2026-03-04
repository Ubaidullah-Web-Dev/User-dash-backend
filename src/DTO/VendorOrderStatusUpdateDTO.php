<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class VendorOrderStatusUpdateDTO
{
    #[Assert\NotBlank(message: 'Status is required')]
    #[Assert\Choice(choices: ['pending', 'approved', 'received', 'cancelled'], message: 'Invalid status')]
    public ?string $status = null;
}