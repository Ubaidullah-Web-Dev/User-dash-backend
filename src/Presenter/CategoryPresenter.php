<?php

namespace App\Presenter;

use App\Entity\Category;

/** Single source of truth for category data: used by the JSON API and by the Twig pages. */
class CategoryPresenter
{
    public function toArray(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'image' => $category->getImage(),
        ];
    }
}
