<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Repository;

use App\Domain\Authorization\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permission>
 */
class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    public function findByName(string $name): ?Permission
    {
        return $this->findOneBy(['name' => strtolower($name)]);
    }

    /**
     * @return array{items: Permission[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function getPaginated(int $page = 1, int $perPage = 20): array
    {
        $query = $this->createQueryBuilder('p')
            ->where('p.status = 1')
            ->orderBy('p.name', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        $paginator = new Paginator($query);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage),
        ];
    }

    public function save(Permission $permission, bool $flush = true): Permission
    {
        $this->getEntityManager()->persist($permission);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $permission;
    }

    public function remove(Permission $permission, bool $flush = true): void
    {
        $this->getEntityManager()->remove($permission);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
