<?php

declare(strict_types=1);

namespace App\Domain\Guild\Repository;

use App\Domain\Guild\Entity\Guild;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Guild>
 */
class GuildRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Guild::class);
    }

    /**
     * Get paginated guild list with member counts.
     *
     * @return array{items: Guild[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findPaginated(int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('g')
            ->leftJoin('g.owner', 'o')
            ->addSelect('o')
            ->leftJoin('g.members', 'm')
            ->addSelect('COUNT(m.id) as HIDDEN memberCount')
            ->groupBy('g.id')
            ->orderBy('memberCount', 'DESC')
            ->addOrderBy('g.id', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: false);
        $total = count($paginator);

        $items = [];
        foreach ($paginator as $guild) {
            $items[] = $guild;
        }

        // Get member counts separately to attach to entities
        $this->attachMemberCounts($items);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Search guilds by name.
     *
     * @return array{items: Guild[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function search(string $term, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('g')
            ->leftJoin('g.owner', 'o')
            ->addSelect('o')
            ->where('g.name LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('g.id', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = count($paginator);

        $items = iterator_to_array($paginator);
        $this->attachMemberCounts($items);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Get guild with the most members.
     */
    public function findMostMembers(): ?Guild
    {
        $result = $this->createQueryBuilder('g')
            ->leftJoin('g.owner', 'o')
            ->addSelect('o')
            ->leftJoin('g.members', 'm')
            ->addSelect('COUNT(m.id) as memberCount')
            ->groupBy('g.id')
            ->orderBy('memberCount', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($result === null) {
            return null;
        }

        // Result is an array [Guild, memberCount]
        if (is_array($result)) {
            $guild = $result[0];
            $guild->setMemberCount((int) $result['memberCount']);
            return $guild;
        }

        return $result;
    }

    /**
     * Get guild with owner and room eagerly loaded.
     */
    public function findWithRelations(int $id): ?Guild
    {
        $guild = $this->createQueryBuilder('g')
            ->leftJoin('g.owner', 'o')
            ->addSelect('o')
            ->leftJoin('g.room', 'r')
            ->addSelect('r')
            ->where('g.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($guild) {
            $this->attachMemberCounts([$guild]);
        }

        return $guild;
    }

    /**
     * Get popular guilds (by member count).
     *
     * @return Guild[]
     */
    public function findPopular(int $limit = 10): array
    {
        $results = $this->createQueryBuilder('g')
            ->leftJoin('g.owner', 'o')
            ->addSelect('o')
            ->leftJoin('g.members', 'm')
            ->addSelect('COUNT(m.id) as memberCount')
            ->groupBy('g.id')
            ->orderBy('memberCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $guilds = [];
        foreach ($results as $result) {
            if (is_array($result)) {
                $guild = $result[0];
                $guild->setMemberCount((int) $result['memberCount']);
                $guilds[] = $guild;
            }
        }

        return $guilds;
    }

    /**
     * Get guilds owned by a user.
     *
     * @return Guild[]
     */
    public function findByOwner(int $userId): array
    {
        $guilds = $this->createQueryBuilder('g')
            ->where('g.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('g.id', 'DESC')
            ->getQuery()
            ->getResult();

        $this->attachMemberCounts($guilds);

        return $guilds;
    }

    /**
     * Count total guilds.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Attach member counts to guild entities.
     *
     * @param Guild[] $guilds
     */
    private function attachMemberCounts(array $guilds): void
    {
        if (empty($guilds)) {
            return;
        }

        $ids = array_map(fn(Guild $g) => $g->getId(), $guilds);

        $counts = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(m.guild) as guildId, COUNT(m.id) as cnt')
            ->from('App\Domain\Guild\Entity\GuildMember', 'm')
            ->where('m.guildId IN (:ids)')
            ->setParameter('ids', $ids)
            ->groupBy('m.guildId')
            ->getQuery()
            ->getResult();

        $countMap = [];
        foreach ($counts as $row) {
            $countMap[$row['guildId']] = (int) $row['cnt'];
        }

        foreach ($guilds as $guild) {
            $guild->setMemberCount($countMap[$guild->getId()] ?? 0);
        }
    }

    public function save(Guild $guild, bool $flush = true): Guild
    {
        $this->getEntityManager()->persist($guild);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $guild;
    }
}
