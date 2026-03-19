<?php

namespace App\Entity;

use App\Repository\RegisteredCustomerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RegisteredCustomerRepository::class)]
class RegisteredCustomer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $labName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column]
    private float $totalSpent = 0.0;

    #[ORM\Column]
    private float $remainingBalance = 0.0;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'registeredCustomer', targetEntity: Order::class)]
    private Collection $orders;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->orders = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): self { $this->name = $name; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(string $phone): self { $this->phone = $phone; return $this; }

    public function getLabName(): ?string { return $this->labName; }
    public function setLabName(?string $labName): self { $this->labName = $labName; return $this; }

    public function getCity(): ?string { return $this->city; }
    public function setCity(?string $city): self { $this->city = $city; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }

    public function getTotalSpent(): float { return $this->totalSpent; }
    public function setTotalSpent(float $totalSpent): self { $this->totalSpent = $totalSpent; return $this; }
    public function addTotalSpent(float $amount): self { $this->totalSpent += $amount; return $this; }

    public function getRemainingBalance(): float { return $this->remainingBalance; }
    public function setRemainingBalance(float $remainingBalance): self { $this->remainingBalance = $remainingBalance; return $this; }
    public function addRemainingBalance(float $amount): self { $this->remainingBalance += $amount; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    /** @return Collection<int, Order> */
    public function getOrders(): Collection { return $this->orders; }

    public function addOrder(Order $order): self
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setRegisteredCustomer($this);
            $this->addTotalSpent($order->getTotal());
        }
        return $this;
    }

    public function removeOrder(Order $order): self
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getRegisteredCustomer() === $this) {
                $order->setRegisteredCustomer(null);
            }
        }
        return $this;
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;
        return $this;
    }
}