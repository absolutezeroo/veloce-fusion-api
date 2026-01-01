<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\UserBadge;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserBadge>
 */
class UserBadgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserBadge::class);
    }

    /**
     * Get slotted badges for a user (visible on profile).
     *
     * @return UserBadge[]
     */
    public function findSlotted(int $userId): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.userId = :userId')
            ->andWhere('b.slotId > 0')
            ->setParameter('userId', $userId)
            ->orderBy('b.slotId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get paginated badge list for a user.
     *
     * @return array{items: UserBadge[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findByUserPaginated(int $userId, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('b.slotId', 'DESC')
            ->addOrderBy('b.id', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Count badges for a user.
     */
    public function countByUser(int $userId): int
    {
        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Check if user has a specific badge.
     */
    public function hasBadge(int $userId, string $badgeCode): bool
    {
        $count = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.userId = :userId')
            ->andWhere('b.badgeCode = :badgeCode')
            ->setParameter('userId', $userId)
            ->setParameter('badgeCode', $badgeCode)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Find a specific badge for a user.
     */
    public function findUserBadge(int $userId, string $badgeCode): ?UserBadge
    {
        return $this->findOneBy([
            'userId' => $userId,
            'badgeCode' => $badgeCode,
        ]);
    }

    public function save(UserBadge $badge, bool $flush = true): UserBadge
    {
        $this->getEntityManager()->persist($badge);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $badge;
    }

    public function remove(UserBadge $badge, bool $flush = true): void
    {
        $this->getEntityManager()->remove($badge);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
