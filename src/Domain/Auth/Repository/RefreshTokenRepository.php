<?php

declare(strict_types=1);

namespace App\Domain\Auth\Repository;

use App\Domain\Auth\Entity\RefreshToken;
use App\Domain\User\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    /**
     * Find a valid (non-expired) refresh token.
     */
    public function findValidToken(string $token): ?RefreshToken
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.token = :token')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get all active refresh tokens for a user.
     *
     * @return RefreshToken[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.userId = :userId')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('userId', $user->getId())
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('rt.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count active sessions for a user.
     */
    public function countActiveByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('rt')
            ->select('COUNT(rt.id)')
            ->where('rt.userId = :userId')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('userId', $user->getId())
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Revoke a specific token.
     */
    public function revoke(RefreshToken $token): void
    {
        $this->getEntityManager()->remove($token);
        $this->getEntityManager()->flush();
    }

    /**
     * Revoke all tokens for a user (logout everywhere).
     */
    public function revokeAllForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.userId = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->execute();
    }

    /**
     * Clean up expired tokens.
     */
    public function deleteExpired(): int
    {
        return (int) $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    public function save(RefreshToken $token, bool $flush = true): RefreshToken
    {
        $this->getEntityManager()->persist($token);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $token;
    }
}
