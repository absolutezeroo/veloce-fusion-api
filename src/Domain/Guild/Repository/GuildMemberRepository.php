<?php

declare(strict_types=1);

namespace App\Domain\Guild\Repository;

use App\Domain\Guild\Entity\GuildMember;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GuildMember>
 */
class GuildMemberRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GuildMember::class);
    }

    /**
     * Get paginated members of a guild.
     *
     * @return array{items: GuildMember[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findByGuildPaginated(int $guildId, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.guildId = :guildId')
            ->setParameter('guildId', $guildId)
            ->orderBy('m.levelId', 'ASC')
            ->addOrderBy('m.memberSince', 'ASC');

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
     * Get guilds a user is a member of (paginated).
     *
     * @return array{items: GuildMember[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findByUserPaginated(int $userId, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.guild', 'g')
            ->addSelect('g')
            ->leftJoin('g.owner', 'o')
            ->addSelect('o')
            ->where('m.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('m.memberSince', 'DESC');

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
     * Count members in a guild.
     */
    public function countByGuild(int $guildId): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.guildId = :guildId')
            ->setParameter('guildId', $guildId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Check if user is member of a guild.
     */
    public function isMember(int $guildId, int $userId): bool
    {
        $count = (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.guildId = :guildId')
            ->andWhere('m.userId = :userId')
            ->setParameter('guildId', $guildId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Get a specific membership.
     */
    public function findMembership(int $guildId, int $userId): ?GuildMember
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.guildId = :guildId')
            ->andWhere('m.userId = :userId')
            ->setParameter('guildId', $guildId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get guild admins (level <= 1).
     *
     * @return GuildMember[]
     */
    public function findAdmins(int $guildId): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.user', 'u')
            ->addSelect('u')
            ->where('m.guildId = :guildId')
            ->andWhere('m.levelId <= 1')
            ->setParameter('guildId', $guildId)
            ->orderBy('m.levelId', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(GuildMember $member, bool $flush = true): GuildMember
    {
        $this->getEntityManager()->persist($member);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $member;
    }

    public function remove(GuildMember $member, bool $flush = true): void
    {
        $this->getEntityManager()->remove($member);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
