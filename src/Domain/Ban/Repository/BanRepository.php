<?php

declare(strict_types=1);

namespace App\Domain\Ban\Repository;

use App\Domain\Ban\Entity\Ban;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ban>
 */
class BanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ban::class);
    }

    /**
     * Find active ban for a user by user ID.
     */
    public function findActiveBanByUserId(int $userId): ?Ban
    {
        return $this->createQueryBuilder('b')
            ->where('b.userId = :userId')
            ->andWhere('(b.expiresAt = 0 OR b.expiresAt > :now)')
            ->setParameter('userId', $userId)
            ->setParameter('now', time())
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active ban by IP address.
     */
    public function findActiveBanByIp(string $ip): ?Ban
    {
        return $this->createQueryBuilder('b')
            ->where('b.ip = :ip')
            ->andWhere('(b.expiresAt = 0 OR b.expiresAt > :now)')
            ->setParameter('ip', $ip)
            ->setParameter('now', time())
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find active ban by machine ID.
     */
    public function findActiveBanByMachineId(string $machineId): ?Ban
    {
        if (empty($machineId)) {
            return null;
        }

        return $this->createQueryBuilder('b')
            ->where('b.machineId = :machineId')
            ->andWhere('(b.expiresAt = 0 OR b.expiresAt > :now)')
            ->setParameter('machineId', $machineId)
            ->setParameter('now', time())
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if user has any active ban (by userId, IP, or machineId).
     */
    public function hasActiveBan(int $userId, ?string $ip = null, ?string $machineId = null): ?Ban
    {
        // Check by user ID first
        $ban = $this->findActiveBanByUserId($userId);
        if ($ban) {
            return $ban;
        }

        // Check by IP
        if ($ip) {
            $ban = $this->findActiveBanByIp($ip);
            if ($ban) {
                return $ban;
            }
        }

        // Check by machine ID
        if ($machineId) {
            $ban = $this->findActiveBanByMachineId($machineId);
            if ($ban) {
                return $ban;
            }
        }

        return null;
    }

    /**
     * Get all bans for a user (active and expired).
     *
     * @return Ban[]
     */
    public function findAllByUserId(int $userId): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get paginated active bans.
     *
     * @return array{items: Ban[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function getActiveBansPaginated(int $page = 1, int $perPage = 20): array
    {
        $query = $this->createQueryBuilder('b')
            ->where('b.expiresAt = 0 OR b.expiresAt > :now')
            ->setParameter('now', time())
            ->orderBy('b.createdAt', 'DESC')
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

    public function save(Ban $ban, bool $flush = true): Ban
    {
        $this->getEntityManager()->persist($ban);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $ban;
    }

    public function remove(Ban $ban, bool $flush = true): void
    {
        $this->getEntityManager()->remove($ban);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
