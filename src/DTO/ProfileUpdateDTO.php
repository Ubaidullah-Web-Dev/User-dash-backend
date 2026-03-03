<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ProfileUpdateDTO
{
    #[Assert\Length(max: 255)]
    public ?string $name = null;

    #[Assert\Length(min: 6)]
    public ?string $password = null;
}