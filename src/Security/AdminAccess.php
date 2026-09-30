<?php

namespace App\Security;

use App\Entity\User;

/**
 * Role flags and path rules ported 1:1 from frontend/app/(tenant)/[companySlug]/admin/layout.tsx.
 *
 * These deliberately use the user's raw roles (as the JWT did), not the role hierarchy:
 * a moderator inherits ROLE_EMPLOYEE, but must still NOT see employee-only pages.
 */
final class AdminAccess
{
    public const MODERATOR_ALLOWED_PATHS = ['/admin/dashboard-lab', '/admin/sales-lab', '/admin/stock', '/admin/reagents'];
    public const EMPLOYEE_ALLOWED_PATHS = ['/admin/dashboard-lab', '/admin/sales-lab', '/admin/customers-lab', '/admin/invoices-lab'];

    public readonly bool $isSuperAdmin;
    public readonly bool $isArchitectural;
    public readonly bool $isAdmin;
    public readonly bool $isModerator;
    public readonly bool $isEmployee;
    public readonly bool $isStandardUser;

    public function __construct(?User $user)
    {
        $roles = $user?->getRoles() ?? [];
        $this->isSuperAdmin = in_array('ROLE_SUPER_ADMIN', $roles, true);
        $this->isArchitectural = in_array('ROLE_ARCHITECTURAL', $roles, true) || $this->isSuperAdmin;
        $this->isAdmin = in_array('ROLE_ADMIN', $roles, true) || $this->isArchitectural;
        $this->isModerator = in_array('ROLE_MODERATOR', $roles, true) && !$this->isAdmin;
        $this->isEmployee = in_array('ROLE_EMPLOYEE', $roles, true) && !$this->isAdmin && !$this->isModerator;
        $this->isStandardUser = in_array('ROLE_USER', $roles, true) && !$this->isAdmin && !$this->isModerator && !$this->isEmployee;
    }

    public function isAuthorized(): bool
    {
        return $this->isAdmin || $this->isModerator || $this->isEmployee || $this->isArchitectural;
    }

    /** Route guard: mirrors the redirect useEffect in admin/layout.tsx (uses `pathname.includes`). */
    public function canVisit(string $pathname): bool
    {
        if (!$this->isArchitectural && str_contains($pathname, '/admin/users')) {
            return false;
        }
        if ($this->isModerator) {
            return $this->matchesAny($pathname, self::MODERATOR_ALLOWED_PATHS, fn ($h, $p) => str_contains($h, $p));
        }
        if ($this->isEmployee) {
            return $this->matchesAny($pathname, self::EMPLOYEE_ALLOWED_PATHS, fn ($h, $p) => str_contains($h, $p));
        }

        return !$this->isStandardUser;
    }

    /** Sidebar visibility: mirrors the allNavItems filter in admin/layout.tsx (uses `href.endsWith`). */
    public function showsNavItem(string $label, string $href): bool
    {
        $allowed = $this->isAdmin;
        if ('Users' === $label) {
            $allowed = $this->isArchitectural;
        }
        if ($this->isModerator) {
            $allowed = $this->matchesAny($href, self::MODERATOR_ALLOWED_PATHS, fn ($h, $p) => str_ends_with($h, $p));
        }
        if ($this->isEmployee) {
            $allowed = $this->matchesAny($href, self::EMPLOYEE_ALLOWED_PATHS, fn ($h, $p) => str_ends_with($h, $p));
        }
        if ($this->isStandardUser) {
            $allowed = false;
        }

        return $allowed;
    }

    public function roleLabel(): string
    {
        return match (true) {
            $this->isSuperAdmin => 'Super Admin',
            $this->isArchitectural => 'Architectural',
            $this->isModerator => 'Moderator',
            $this->isEmployee => 'Lab Employee',
            default => 'Admin',
        };
    }

    private function matchesAny(string $haystack, array $paths, callable $match): bool
    {
        foreach ($paths as $path) {
            if ($match($haystack, $path)) {
                return true;
            }
        }

        return false;
    }
}
