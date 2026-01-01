<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Repository;

use App\Domain\Authorization\Entity\RoleRank;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoleRank>
 */
class RoleRankRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoleRank::class);
    }

    /**
     * Get all role IDs assigned to a user rank.
     *
     * @return int[]
     */
    public function getRoleIdsByRank(int $rankId): array
    {
        $results = $this->createQueryBuilder('rr')
            ->select('rr.roleId')
            ->where('rr.rankId = :rankId')
            ->setParameter('rankId', $rankId)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'roleId');
    }

    /**
     * Find existing role-rank link.
     */
    public function findByRoleAndRank(int $roleId, int $rankId): ?RoleRank
    {
        return $this->findOneBy([
            'roleId' => $roleId,
            'rankId' => $rankId,
        ]);
    }

    /**
     * Get all permissions for a user rank (resolves roles and returns permission names).
     *
     * @return string[]
     */
    public function getPermissionNamesByRank(int $rankId, RoleHierarchyRepository $hierarchyRepo, RolePermissionRepository $permissionRepo, PermissionRepository $permRepo): array
    {
        $roleIds = $this->getRoleIdsByRank($rankId);

        if (empty($roleIds)) {
            return [];
        }

        // Resolve hierarchy to get all inherited roles
        $allRoleIds = $hierarchyRepo->getAllRoleIdsWithHierarchy($roleIds);

        // Get permission IDs
        $permissionIds = $permissionRepo->getPermissionIdsByRoles($allRoleIds);

        if (empty($permissionIds)) {
            return [];
        }

        // Get permission names
        $permissions = $permRepo->findBy(['id' => $permissionIds, 'status' => 1]);

        return array_map(fn($p) => $p->name, $permissions);
    }

    public function save(RoleRank $roleRank, bool $flush = true): RoleRank
    {
        $this->getEntityManager()->persist($roleRank);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $roleRank;
    }

    public function remove(RoleRank $roleRank, bool $flush = true): void
    {
        $this->getEntityManager()->remove($roleRank);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
