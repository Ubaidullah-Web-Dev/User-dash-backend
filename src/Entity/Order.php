<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $address = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customerName = null;

    #[ORM\Column(length: 50)]
    private ?string $phone = null;

    #[ORM\Column]
    private ?float $total = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: RegisteredCustomer::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: true)]
    private ?RegisteredCustomer $registeredCustomer = null;

    #[ORM\ManyToOne(targetEntity: GuestUser::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: true)]
    private ?GuestUser $guestUser = null;

    #[ORM\Column(nullable: true)]
    private ?float $amountTendered = null;

    #[ORM\Column(nullable: true)]
    private ?float $changeDue = null;

    #[ORM\Column(nullable: true)]
    private ?float $discountPercentage = null;

    #[ORM\Column(nullable: true)]
    private ?float $discountAmount = null;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist', 'remove'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(string $address): self { $this->address = $address; return $this; }

    public function getCustomerName(): ?string { return $this->customerName; }
    public function setCustomerName(?string $customerName): self { $this->customerName = $customerName; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(string $phone): self { $this->phone = $phone; return $this; }

    public function getTotal(): ?float { return $this->total; }
    public function setTotal(float $total): self { $this->total = $total; return $this; }

    public function getRegisteredCustomer(): ?RegisteredCustomer { return $this->registeredCustomer; }
    public function setRegisteredCustomer(?RegisteredCustomer $registeredCustomer): self { $this->registeredCustomer = $registeredCustomer; return $this; }

    public function getGuestUser(): ?GuestUser { return $this->guestUser; }
    public function setGuestUser(?GuestUser $guestUser): self { $this->guestUser = $guestUser; return $this; }

    public function getAmountTendered(): ?float { return $this->amountTendered; }
    public function setAmountTendered(?float $amountTendered): self { $this->amountTendered = $amountTendered; return $this; }

    public function getChangeDue(): ?float { return $this->changeDue; }
    public function setChangeDue(?float $changeDue): self { $this->changeDue = $changeDue; return $this; }

    public function getDiscountPercentage(): ?float { return $this->discountPercentage; }
    public function setDiscountPercentage(?float $discountPercentage): self { $this->discountPercentage = $discountPercentage; return $this; }

    public function getDiscountAmount(): ?float { return $this->discountAmount; }
    public function setDiscountAmount(?float $discountAmount): self { $this->discountAmount = $discountAmount; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    /** @return Collection<int, OrderItem> */
    public function getItems(): Collection { return $this->items; }

    public function addItem(OrderItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }
        return $this;
    }
}