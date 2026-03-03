<?php

namespace App\DTO;

use App\Entity\Category;

class CategoryDTO
{
    public int $id;
    public string $name;
    public string $slug;
    public ?string $image;

    public static function fromEntity(Category $category): self
    {
        $dto = new self();
        $dto->id = $category->getId();
        $dto->name = $category->getName() ?: '';
        $dto->slug = $category->getSlug() ?: '';
        $dto->image = $category->getImage();

        return $dto;
    }
}