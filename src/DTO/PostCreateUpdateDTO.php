<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class PostCreateUpdateDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $title = null;

    #[Assert\NotBlank]
    public ?string $content = null;
}