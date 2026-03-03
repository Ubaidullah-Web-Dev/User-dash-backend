<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateRolesDTO
{
    #[Assert\NotBlank]
    #[Assert\Type('array')]
    public ?array $roles = null;
}