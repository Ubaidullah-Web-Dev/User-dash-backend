<?php

namespace App\DTO;

use App\Entity\Product;
use App\Entity\ProductImage;

class ProductDTO
{
    public int $id;
    public string $name;
    public string $slug;
    public string $description;
    public string $price;
    public int $stock;
    public bool $isRecommended;
    public bool $isActive;
    public bool $isOutOfStock;
    public string $createdAt;
    public array $category;
    public array $seller;
    public array $images;
    public ?string $unit;

    public static function fromEntity(Product $product): self
    {
        $dto = new self();
        $dto->id = $product->getId();
        $dto->name = $product->getName();
        $dto->slug = $product->getSlug();
        $dto->description = $product->getDescription();
        $dto->price = $product->getPrice();
        $dto->stock = $product->getStock();
        $dto->isRecommended = $product->isRecommended();
        $dto->isActive = $product->isActive();
        $dto->isOutOfStock = $product->getStock() <= 0;
        $dto->createdAt = $product->getCreatedAt()->format('Y-m-d H:i:s');
        $dto->category = [
            'id' => $product->getCategory()?->getId(),
            'name' => $product->getCategory()?->getName(),
        ];
        $dto->seller = [
            'id' => $product->getUser()?->getId(),
            'name' => $product->getUser()?->getName(),
        ];
        $dto->images = array_map(function (ProductImage $img) {
            return $img->getUrl();
        }, $product->getImages()->toArray());
        $dto->unit = $product->getUnit();

        return $dto;
    }
}