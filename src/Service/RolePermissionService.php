<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpKernel\KernelInterface;

class RolePermissionService
{
    private array $rolesConfig;

    public function __construct(KernelInterface $kernel)
    {
        $configPath = $kernel->getProjectDir() . '/config/roles_permissions.yaml';
        $this->rolesConfig = Yaml::parseFile($configPath)['roles'] ?? [];
    }

    public function hasPermission(array $userRoles, string $permission): bool
    {
        foreach ($userRoles as $role) {
            if (isset($this->rolesConfig[$role]['permissions'])) {
                if (in_array($permission, $this->rolesConfig[$role]['permissions'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getPermissionsForRole(string $role): array
    {
        return $this->rolesConfig[$role]['permissions'] ?? [];
    }

    public function getAllRoles(): array
    {
        return array_keys($this->rolesConfig);
    }
}