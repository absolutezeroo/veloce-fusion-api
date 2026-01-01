<?php

declare(strict_types=1);

namespace App\Domain\Messenger\Repository;

use App\Domain\Messenger\Entity\MessengerFriendship;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessengerFriendship>
 */
class MessengerFriendshipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessengerFriendship::class);
    }

    /**
     * Get paginated friends for a user.
     *
     * @return array{items: MessengerFriendship[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findFriendsPaginated(int $userId, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.userTwo', 'u')
            ->addSelect('u')
            ->where('f.userOneId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('u.online', 'DESC')
            ->addOrderBy('u.username', 'ASC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
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
     * Get online friends for a user.
     *
     * @return MessengerFriendship[]
     */
    public function findOnlineFriends(int $userId, int $limit = 20): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.userTwo', 'u')
            ->addSelect('u')
            ->where('f.userOneId = :userId')
            ->andWhere('u.online = :online')
            ->setParameter('userId', $userId)
            ->setParameter('online', true)
            ->orderBy('u.username', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count friends for a user.
     */
    public function countFriends(int $userId): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.userOneId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Check if two users are friends.
     */
    public function areFriends(int $userOneId, int $userTwoId): bool
    {
        $count = (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.userOneId = :userOneId')
            ->andWhere('f.userTwoId = :userTwoId')
            ->setParameter('userOneId', $userOneId)
            ->setParameter('userTwoId', $userTwoId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Find a specific friendship.
     */
    public function findFriendship(int $userOneId, int $userTwoId): ?MessengerFriendship
    {
        return $this->findOneBy([
            'userOneId' => $userOneId,
            'userTwoId' => $userTwoId,
        ]);
    }

    public function save(MessengerFriendship $friendship, bool $flush = true): MessengerFriendship
    {
        $this->getEntityManager()->persist($friendship);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $friendship;
    }

    public function remove(MessengerFriendship $friendship, bool $flush = true): void
    {
        $this->getEntityManager()->remove($friendship);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
