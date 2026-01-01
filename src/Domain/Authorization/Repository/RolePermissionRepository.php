<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Repository;

use App\Domain\Authorization\Entity\RolePermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RolePermission>
 */
class RolePermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RolePermission::class);
    }

    /**
     * Check if a permission is assigned to any of the given roles.
     *
     * @param int[] $roleIds
     */
    public function isPermissionAssigned(int $permissionId, array $roleIds): bool
    {
        if (empty($roleIds)) {
            return false;
        }

        $count = $this->createQueryBuilder('rp')
            ->select('COUNT(rp.id)')
            ->where('rp.permissionId = :permissionId')
            ->andWhere('rp.roleId IN (:roleIds)')
            ->setParameter('permissionId', $permissionId)
            ->setParameter('roleIds', $roleIds)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * Find existing role-permission link.
     */
    public function findByRoleAndPermission(int $roleId, int $permissionId): ?RolePermission
    {
        return $this->findOneBy([
            'roleId' => $roleId,
            'permissionId' => $permissionId,
        ]);
    }

    /**
     * Get all permission IDs for given roles.
     *
     * @param int[] $roleIds
     * @return int[]
     */
    public function getPermissionIdsByRoles(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        $results = $this->createQueryBuilder('rp')
            ->select('DISTINCT rp.permissionId')
            ->where('rp.roleId IN (:roleIds)')
            ->setParameter('roleIds', $roleIds)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'permissionId');
    }

    public function save(RolePermission $rolePermission, bool $flush = true): RolePermission
    {
        $this->getEntityManager()->persist($rolePermission);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $rolePermission;
    }

    public function remove(RolePermission $rolePermission, bool $flush = true): void
    {
        $this->getEntityManager()->remove($rolePermission);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
