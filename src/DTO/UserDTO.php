<?php

namespace App\DTO;

use App\Entity\User;

class UserDTO
{
    public int $id;
    public string $email;
    public string $name;
    public array $roles;

    public function __construct(int $id, string $email, string $name, array $roles)
    {
        $this->id = $id;
        $this->email = $email;
        $this->name = $name;
        $this->roles = $roles;
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            $user->getId(),
            $user->getEmail(),
            $user->getName() ?: '',
            $user->getRoles()
            );
    }
}