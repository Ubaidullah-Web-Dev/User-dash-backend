<?php

namespace App\EventSubscriber;

use App\Entity\VendorOrder;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;

class VendorOrderStockSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
        ];
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof VendorOrder) {
            return;
        }

        if ($entity->getStatus() === 'received') {
            $this->incrementStock($entity);
            if ($entity->getReceivedAt() === null) {
                $entity->setReceivedAt(new \DateTimeImmutable());
            }
        }
    }


    private function incrementStock(VendorOrder $vendorOrder): void
    {
        $product = $vendorOrder->getProduct();
        if ($product) {
            $product->setStock($product->getStock() + $vendorOrder->getQuantity());
        }
    }
}