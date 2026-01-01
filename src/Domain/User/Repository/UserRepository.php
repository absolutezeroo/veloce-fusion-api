<?php

declare(strict_types=1);

namespace App\Domain\User\Repository;

use App\Domain\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => strtolower($username)]);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['mail' => strtolower($email)]);
    }

    public function countByIp(string $ipAddress): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.ipRegister = :ip')
            ->setParameter('ip', $ipAddress)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count total users.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count users currently online.
     */
    public function countOnline(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.online = :online')
            ->setParameter('online', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Search users by username.
     *
     * @return array{items: User[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function search(string $term, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.username LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('u.id', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(u.id)')
            ->setFirstResult(0)
            ->setMaxResults(null)
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb->getQuery()->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Get paginated online users.
     *
     * @return array{items: User[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findOnlinePaginated(int $page = 1, int $perPage = 50): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.online = :online')
            ->setParameter('online', true)
            ->orderBy('u.id', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(u.id)')
            ->setFirstResult(0)
            ->setMaxResults(null)
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb->getQuery()->getResult();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    public function save(User $user, bool $flush = true): User
    {
        $this->getEntityManager()->persist($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $user;
    }
}
