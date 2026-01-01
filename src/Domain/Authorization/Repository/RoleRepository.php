<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Repository;

use App\Domain\Authorization\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    public function findByName(string $name): ?Role
    {
        return $this->findOneBy(['name' => strtolower($name)]);
    }

    /**
     * @return array{items: Role[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function getPaginated(int $page = 1, int $perPage = 20): array
    {
        $query = $this->createQueryBuilder('r')
            ->where('r.status = 1')
            ->orderBy('r.name', 'ASC')
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

    public function save(Role $role, bool $flush = true): Role
    {
        $this->getEntityManager()->persist($role);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $role;
    }

    public function remove(Role $role, bool $flush = true): void
    {
        $this->getEntityManager()->remove($role);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
