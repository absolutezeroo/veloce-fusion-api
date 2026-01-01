<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Service;

use App\Domain\Authorization\Repository\PermissionRepository;
use App\Domain\Authorization\Repository\RoleHierarchyRepository;
use App\Domain\Authorization\Repository\RolePermissionRepository;
use App\Domain\Authorization\Repository\RoleRankRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Resolves permissions for user ranks using the RBAC hierarchy.
 * Results are cached for performance.
 */
final class PermissionResolver
{
    private const CACHE_TTL = 1800; // 30 minutes

    public function __construct(
        private readonly PermissionRepository $permissionRepository,
        private readonly RoleRankRepository $roleRankRepository,
        private readonly RoleHierarchyRepository $roleHierarchyRepository,
        private readonly RolePermissionRepository $rolePermissionRepository,
        private readonly CacheInterface $cache,
    ) {}

    /**
     * Check if a user rank has a specific permission.
     */
    public function hasPermission(int $userRank, string $permissionName): bool
    {
        // Fast path: no permission specified = allow access
        if (empty($permissionName)) {
            return true;
        }

        $cacheKey = sprintf('permission_check_%d_%s', $userRank, md5($permissionName));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($userRank, $permissionName): bool {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->doCheckPermission($userRank, $permissionName);
        });
    }

    /**
     * Get all permission names for a user rank.
     *
     * @return string[]
     */
    public function getPermissionsForRank(int $userRank): array
    {
        $cacheKey = sprintf('permissions_for_rank_%d', $userRank);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($userRank): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->doGetPermissions($userRank);
        });
    }

    /**
     * Clear cached permissions (call when roles/permissions change).
     */
    public function clearCache(?int $userRank = null): void
    {
        if ($userRank !== null) {
            $this->cache->delete(sprintf('permissions_for_rank_%d', $userRank));
        }

        // For full cache clear, rely on cache TTL or implement tag-based invalidation
    }

    private function doCheckPermission(int $userRank, string $permissionName): bool
    {
        // Find the permission
        $permission = $this->permissionRepository->findByName($permissionName);

        // If permission doesn't exist in DB, assume it's not required (allow access)
        if ($permission === null) {
            return true;
        }

        // If permission is inactive, allow access
        if (!$permission->isActive()) {
            return true;
        }

        // Get roles assigned to this rank
        $roleIds = $this->roleRankRepository->getRoleIdsByRank($userRank);

        if (empty($roleIds)) {
            return false;
        }

        // Resolve role hierarchy (get all inherited roles)
        $allRoleIds = $this->roleHierarchyRepository->getAllRoleIdsWithHierarchy($roleIds);

        // Check if permission is assigned to any of these roles
        return $this->rolePermissionRepository->isPermissionAssigned($permission->getId(), $allRoleIds);
    }

    private function doGetPermissions(int $userRank): array
    {
        // Get roles assigned to this rank
        $roleIds = $this->roleRankRepository->getRoleIdsByRank($userRank);

        if (empty($roleIds)) {
            return [];
        }

        // Resolve role hierarchy
        $allRoleIds = $this->roleHierarchyRepository->getAllRoleIdsWithHierarchy($roleIds);

        // Get all permission IDs for these roles
        $permissionIds = $this->rolePermissionRepository->getPermissionIdsByRoles($allRoleIds);

        if (empty($permissionIds)) {
            return [];
        }

        // Get active permission names
        $permissions = $this->permissionRepository->findBy([
            'id' => $permissionIds,
            'status' => 1,
        ]);

        return array_map(fn($p) => $p->name, $permissions);
    }
}
