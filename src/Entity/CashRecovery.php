<?php

namespace App\Entity;

use App\Entity\Company;
use App\Entity\User;
use App\Entity\RegisteredCustomer;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\CashRecoveryRepository::class)]
class CashRecovery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?float $amount = null;

    #[ORM\ManyToOne(targetEntity: RegisteredCustomer::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?RegisteredCustomer $registeredCustomer = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Company $company = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $remarks = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getAmount(): ?float { return $this->amount; }
    public function setAmount(float $amount): self { $this->amount = $amount; return $this; }

    public function getRegisteredCustomer(): ?RegisteredCustomer { return $this->registeredCustomer; }
    public function setRegisteredCustomer(?RegisteredCustomer $registeredCustomer): self { $this->registeredCustomer = $registeredCustomer; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getCompany(): ?Company { return $this->company; }
    public function setCompany(?Company $company): self { $this->company = $company; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getRemarks(): ?string { return $this->remarks; }
    public function setRemarks(?string $remarks): self { $this->remarks = $remarks; return $this; }
}
