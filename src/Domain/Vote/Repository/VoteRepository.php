<?php

declare(strict_types=1);

namespace App\Domain\Vote\Repository;

use App\Domain\Vote\Entity\Vote;
use App\Domain\Vote\Enum\VoteEntity;
use App\Domain\Vote\Enum\VoteType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vote>
 */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    /**
     * Find existing vote by user on an entity.
     */
    public function findExistingVote(int $userId, int $entityId, VoteEntity $voteEntity): ?Vote
    {
        return $this->createQueryBuilder('v')
            ->where('v.userId = :userId')
            ->andWhere('v.entityId = :entityId')
            ->andWhere('v.voteEntity = :voteEntity')
            ->setParameter('userId', $userId)
            ->setParameter('entityId', $entityId)
            ->setParameter('voteEntity', $voteEntity)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if user has voted on an entity.
     */
    public function hasVoted(int $userId, int $entityId, VoteEntity $voteEntity): bool
    {
        $count = (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.userId = :userId')
            ->andWhere('v.entityId = :entityId')
            ->andWhere('v.voteEntity = :voteEntity')
            ->setParameter('userId', $userId)
            ->setParameter('entityId', $entityId)
            ->setParameter('voteEntity', $voteEntity)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Get user's vote type on an entity.
     */
    public function getUserVoteType(int $userId, int $entityId, VoteEntity $voteEntity): ?VoteType
    {
        $vote = $this->findExistingVote($userId, $entityId, $voteEntity);
        return $vote?->voteType;
    }

    /**
     * Count likes for a specific entity.
     */
    public function countLikes(int $entityId, VoteEntity $voteEntity): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.entityId = :entityId')
            ->andWhere('v.voteEntity = :voteEntity')
            ->andWhere('v.voteType = :voteType')
            ->setParameter('entityId', $entityId)
            ->setParameter('voteEntity', $voteEntity)
            ->setParameter('voteType', VoteType::LIKE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count dislikes for a specific entity.
     */
    public function countDislikes(int $entityId, VoteEntity $voteEntity): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.entityId = :entityId')
            ->andWhere('v.voteEntity = :voteEntity')
            ->andWhere('v.voteType = :voteType')
            ->setParameter('entityId', $entityId)
            ->setParameter('voteEntity', $voteEntity)
            ->setParameter('voteType', VoteType::DISLIKE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get vote counts for a single entity.
     *
     * @return array{likes: int, dislikes: int}
     */
    public function getVoteCountsForEntity(int $entityId, VoteEntity $voteEntity): array
    {
        $result = ['likes' => 0, 'dislikes' => 0];

        $votes = $this->createQueryBuilder('v')
            ->select('v.voteType, COUNT(v.id) as count')
            ->where('v.entityId = :entityId')
            ->andWhere('v.voteEntity = :voteEntity')
            ->setParameter('entityId', $entityId)
            ->setParameter('voteEntity', $voteEntity)
            ->groupBy('v.voteType')
            ->getQuery()
            ->getResult();

        foreach ($votes as $vote) {
            if ($vote['voteType'] === VoteType::LIKE) {
                $result['likes'] = (int) $vote['count'];
            } else {
                $result['dislikes'] = (int) $vote['count'];
            }
        }

        return $result;
    }

    /**
     * Get vote counts for multiple entities at once.
     *
     * @param int[] $entityIds
     * @return array<int, array{likes: int, dislikes: int}>
     */
    public function getVoteCountsForEntities(array $entityIds, VoteEntity $voteEntity): array
    {
        if (empty($entityIds)) {
            return [];
        }

        // Initialize result array
        $result = [];
        foreach ($entityIds as $id) {
            $result[$id] = ['likes' => 0, 'dislikes' => 0];
        }

        $votes = $this->createQueryBuilder('v')
            ->select('v.entityId, v.voteType, COUNT(v.id) as count')
            ->where('v.entityId IN (:entityIds)')
            ->andWhere('v.voteEntity = :voteEntity')
            ->setParameter('entityIds', $entityIds)
            ->setParameter('voteEntity', $voteEntity)
            ->groupBy('v.entityId, v.voteType')
            ->getQuery()
            ->getResult();

        foreach ($votes as $vote) {
            $entityId = (int) $vote['entityId'];
            if ($vote['voteType'] === VoteType::LIKE) {
                $result[$entityId]['likes'] = (int) $vote['count'];
            } else {
                $result[$entityId]['dislikes'] = (int) $vote['count'];
            }
        }

        return $result;
    }

    /**
     * Get all votes by a user.
     *
     * @return Vote[]
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('v.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(Vote $vote, bool $flush = true): Vote
    {
        $this->getEntityManager()->persist($vote);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $vote;
    }

    public function remove(Vote $vote, bool $flush = true): void
    {
        $this->getEntityManager()->remove($vote);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
