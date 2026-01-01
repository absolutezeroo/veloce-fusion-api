<?php

declare(strict_types=1);

namespace App\Domain\Room\Repository;

use App\Domain\Room\Entity\Room;
use App\Domain\Room\Enum\RoomState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Room>
 */
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Room::class);
    }

    /**
     * Get paginated room list.
     *
     * @return array{items: Room[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findPaginated(int $page = 1, int $perPage = 20, ?int $categoryId = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.owner', 'o')
            ->addSelect('o')
            ->where('r.state != :invisible')
            ->setParameter('invisible', RoomState::INVISIBLE)
            ->orderBy('r.users', 'DESC')
            ->addOrderBy('r.id', 'DESC');

        if ($categoryId !== null) {
            $qb->andWhere('r.categoryId = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

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
     * Search rooms by name using LIKE.
     *
     * @return array{items: Room[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function search(string $term, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.owner', 'o')
            ->addSelect('o')
            ->where('r.state != :invisible')
            ->andWhere('r.name LIKE :term OR r.description LIKE :term')
            ->setParameter('invisible', RoomState::INVISIBLE)
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('r.users', 'DESC')
            ->addOrderBy('r.score', 'DESC');

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
     * Get rooms owned by a specific user.
     *
     * @return array{items: Room[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findByOwner(int $ownerId, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.owner', 'o')
            ->addSelect('o')
            ->where('r.ownerId = :ownerId')
            ->setParameter('ownerId', $ownerId)
            ->orderBy('r.id', 'DESC');

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
     * Get the most visited room.
     */
    public function findMostVisited(): ?Room
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.owner', 'o')
            ->addSelect('o')
            ->where('r.state = :open')
            ->setParameter('open', RoomState::OPEN)
            ->orderBy('r.users', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get popular rooms (highest score).
     *
     * @return Room[]
     */
    public function findPopular(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.owner', 'o')
            ->addSelect('o')
            ->where('r.state = :open')
            ->setParameter('open', RoomState::OPEN)
            ->orderBy('r.score', 'DESC')
            ->addOrderBy('r.users', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get rooms with users online.
     *
     * @return Room[]
     */
    public function findWithUsers(int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.owner', 'o')
            ->addSelect('o')
            ->where('r.users > 0')
            ->andWhere('r.state != :invisible')
            ->setParameter('invisible', RoomState::INVISIBLE)
            ->orderBy('r.users', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get room with owner and guild eagerly loaded.
     */
    public function findWithOwner(int $id): ?Room
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.owner', 'o')
            ->addSelect('o')
            ->leftJoin('r.guild', 'g')
            ->addSelect('g')
            ->where('r.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count total rooms.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count public rooms.
     */
    public function countPublic(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.state = :open')
            ->setParameter('open', RoomState::OPEN)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count rooms owned by a user.
     */
    public function countByOwner(int $ownerId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.ownerId = :ownerId')
            ->setParameter('ownerId', $ownerId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function save(Room $room, bool $flush = true): Room
    {
        $this->getEntityManager()->persist($room);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $room;
    }
}
