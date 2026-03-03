<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Service\RolePermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class PermissionVoter extends Voter
{
    public function __construct(
        private RolePermissionService $rolePermissionService
        )
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'POST_') || str_starts_with($attribute, 'USER_');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $userRoles = $user->getRoles();

        if (in_array('ROLE_SUPER_ADMIN', $userRoles)) {
            return true;
        }

        if ($this->rolePermissionService->hasPermission($userRoles, $attribute)) {
            return true;
        }

        if ($attribute === 'POST_EDIT' && $subject !== null) {
            if ($this->rolePermissionService->hasPermission($userRoles, 'POST_EDIT_ALL')) {
                return true;
            }

            if ($this->rolePermissionService->hasPermission($userRoles, 'POST_EDIT_OWN')) {
                return method_exists($subject, 'getAuthor') &&
                    $subject->getAuthor() &&
                    $subject->getAuthor()->getId() === $user->getId();
            }
        }

        return false;
    }
}