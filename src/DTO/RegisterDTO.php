<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterDTO
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Regex(
        pattern: '/@(gmail\.com|outlook\.com|yahoo\.com)$/',
        message: 'Only Gmail, Outlook, and Yahoo emails are allowed.'
    )]
    public ?string $email = null;

    #[Assert\NotBlank]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6)]
    public ?string $password = null;

    #[Assert\NotBlank]
    public ?string $confirmPassword = null;
}