<?php

namespace App\DTO;

use App\Entity\CartItem;

class CartItemDTO
{
    public int $id;
    public int $productId;
    public string $productName;
    public string $productPrice;
    public ?string $productImage;
    public int $quantity;
    public bool $isSavedForLater;

    public static function fromEntity(CartItem $item): self
    {
        $dto = new self();
        $dto->id = $item->getId();
        $dto->productId = $item->getProduct()?->getId() ?: 0;
        $dto->productName = $item->getProduct()?->getName() ?: 'Unknown';
        $dto->productPrice = $item->getProduct()?->getPrice() ?: '0.00';
        $firstImage = $item->getProduct()?->getImages()->first();
        $dto->productImage = $firstImage ? $firstImage->getUrl() : null;
        $dto->quantity = $item->getQuantity();
        $dto->isSavedForLater = $item->isSavedForLater();

        return $dto;
    }
}