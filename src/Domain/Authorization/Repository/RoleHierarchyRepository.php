<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Repository;

use App\Domain\Authorization\Entity\RoleHierarchy;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoleHierarchy>
 */
class RoleHierarchyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoleHierarchy::class);
    }

    /**
     * Get all role IDs including inherited ones (children).
     * Recursively resolves the hierarchy.
     *
     * @param int[] $roleIds Starting role IDs
     * @return int[] All role IDs including inherited
     */
    public function getAllRoleIdsWithHierarchy(array $roleIds): array
    {
        if (empty($roleIds)) {
            return [];
        }

        $allRoleIds = $roleIds;
        $toProcess = $roleIds;
        $processed = [];

        while (!empty($toProcess)) {
            $currentId = array_shift($toProcess);

            if (in_array($currentId, $processed, true)) {
                continue;
            }

            $processed[] = $currentId;

            // Get child roles (roles that this role inherits from)
            $childRoleIds = $this->getChildRoleIds($currentId);

            foreach ($childRoleIds as $childId) {
                if (!in_array($childId, $allRoleIds, true)) {
                    $allRoleIds[] = $childId;
                    $toProcess[] = $childId;
                }
            }
        }

        return array_unique($allRoleIds);
    }

    /**
     * Get direct child role IDs for a parent role.
     *
     * @return int[]
     */
    public function getChildRoleIds(int $parentRoleId): array
    {
        $results = $this->createQueryBuilder('rh')
            ->select('rh.childRoleId')
            ->where('rh.parentRoleId = :parentId')
            ->setParameter('parentId', $parentRoleId)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'childRoleId');
    }

    /**
     * Check if adding a hierarchy would create a cycle.
     */
    public function wouldCreateCycle(int $parentRoleId, int $childRoleId): bool
    {
        // Can't be parent of yourself
        if ($parentRoleId === $childRoleId) {
            return true;
        }

        // Check if child already has parent as a descendant (would create cycle)
        $descendantsOfChild = $this->getAllRoleIdsWithHierarchy([$childRoleId]);

        return in_array($parentRoleId, $descendantsOfChild, true);
    }

    /**
     * Check if hierarchy already exists.
     */
    public function hierarchyExists(int $parentRoleId, int $childRoleId): bool
    {
        return $this->findOneBy([
            'parentRoleId' => $parentRoleId,
            'childRoleId' => $childRoleId,
        ]) !== null;
    }

    public function save(RoleHierarchy $hierarchy, bool $flush = true): RoleHierarchy
    {
        $this->getEntityManager()->persist($hierarchy);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $hierarchy;
    }

    public function remove(RoleHierarchy $hierarchy, bool $flush = true): void
    {
        $this->getEntityManager()->remove($hierarchy);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
