<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordDTO
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public ?string $email = null;

    #[Assert\NotBlank]
    public ?string $code = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6)]
    public ?string $password = null;
}