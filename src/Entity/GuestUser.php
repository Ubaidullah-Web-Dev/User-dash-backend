<?php

namespace App\Entity;

use App\Repository\GuestUserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GuestUserRepository::class)]
class GuestUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'guestUser', targetEntity: Order::class)]
    private Collection $orders;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->orders = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    /** @return Collection<int, Order> */
    public function getOrders(): Collection { return $this->orders; }

    public function addOrder(Order $order): self
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setGuestUser($this);
        }
        return $this;
    }

    public function removeOrder(Order $order): self
    {
        if ($this->orders->removeElement($order)) {
            if ($order->getGuestUser() === $this) {
                $order->setGuestUser(null);
            }
        }
        return $this;
    }
}