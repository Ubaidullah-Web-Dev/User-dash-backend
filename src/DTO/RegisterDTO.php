<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterDTO
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    #[Assert\NotBlank]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6)]
    public ?string $password = null;

    #[Assert\NotBlank]
    public ?string $confirmPassword = null;
}