<?php

declare(strict_types=1);

namespace App\Domain\Forum\Repository;

use App\Domain\Forum\Entity\ForumThread;
use App\Domain\Forum\Enum\ThreadStatus;
use App\Domain\Vote\Enum\VoteEntity;
use App\Domain\Vote\Repository\VoteRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumThread>
 */
class ForumThreadRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly VoteRepository $voteRepository,
    ) {
        parent::__construct($registry, ForumThread::class);
    }

    /**
     * Find thread by slug.
     */
    public function findBySlug(string $slug): ?ForumThread
    {
        $thread = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.lastPostUser', 'lpu')
            ->addSelect('u', 'c', 'lpu')
            ->where('t.slug = :slug')
            ->setParameter('slug', strtolower($slug))
            ->getQuery()
            ->getOneOrNullResult();

        if ($thread) {
            $this->addVoteCountsToThread($thread);
        }

        return $thread;
    }

    /**
     * Find thread by category and slug.
     */
    public function findByCategoryAndSlug(int $categoryId, string $slug): ?ForumThread
    {
        $thread = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.lastPostUser', 'lpu')
            ->addSelect('u', 'c', 'lpu')
            ->where('t.categoryId = :categoryId')
            ->andWhere('t.slug = :slug')
            ->setParameter('categoryId', $categoryId)
            ->setParameter('slug', strtolower($slug))
            ->getQuery()
            ->getOneOrNullResult();

        if ($thread) {
            $this->addVoteCountsToThread($thread);
        }

        return $thread;
    }

    /**
     * Get thread with full details for viewing.
     */
    public function findWithDetails(int $id): ?ForumThread
    {
        $thread = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.lastPostUser', 'lpu')
            ->addSelect('u', 'c', 'lpu')
            ->where('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($thread) {
            $this->addVoteCountsToThread($thread);
        }

        return $thread;
    }

    /**
     * Get paginated threads for a category.
     *
     * @return array{items: ForumThread[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findByCategoryPaginated(int $categoryId, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.lastPostUser', 'lpu')
            ->addSelect('u', 'lpu')
            ->where('t.categoryId = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('t.isPinned', 'DESC')
            ->addOrderBy('t.lastPostAt', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = count($paginator);

        $threads = iterator_to_array($paginator);
        $this->addVoteCounts($threads);

        return [
            'items' => $threads,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Get recent threads across all categories.
     *
     * @return array{items: ForumThread[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findRecentPaginated(int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.lastPostUser', 'lpu')
            ->addSelect('u', 'c', 'lpu')
            ->orderBy('t.lastPostAt', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = count($paginator);

        $threads = iterator_to_array($paginator);
        $this->addVoteCounts($threads);

        return [
            'items' => $threads,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Get hot/trending threads.
     *
     * @return ForumThread[]
     */
    public function findHot(int $limit = 10): array
    {
        $threads = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.category', 'c')
            ->addSelect('u', 'c')
            ->where('t.isHot = :hot')
            ->setParameter('hot', true)
            ->orderBy('t.lastPostAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $this->addVoteCounts($threads);

        return $threads;
    }

    /**
     * Get pinned threads for a category.
     *
     * @return ForumThread[]
     */
    public function findPinned(int $categoryId): array
    {
        $threads = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->addSelect('u')
            ->where('t.categoryId = :categoryId')
            ->andWhere('t.isPinned = :pinned')
            ->setParameter('categoryId', $categoryId)
            ->setParameter('pinned', true)
            ->orderBy('t.lastPostAt', 'DESC')
            ->getQuery()
            ->getResult();

        $this->addVoteCounts($threads);

        return $threads;
    }

    /**
     * Get threads by user.
     *
     * @return array{items: ForumThread[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findByUserPaginated(int $userId, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.category', 'c')
            ->addSelect('c')
            ->where('t.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.createdAt', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = count($paginator);

        $threads = iterator_to_array($paginator);
        $this->addVoteCounts($threads);

        return [
            'items' => $threads,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Search threads by title or content.
     *
     * @return array{items: ForumThread[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function search(string $query, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.category', 'c')
            ->addSelect('u', 'c')
            ->where('t.title LIKE :query OR t.content LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.lastPostAt', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = count($paginator);

        $threads = iterator_to_array($paginator);
        $this->addVoteCounts($threads);

        return [
            'items' => $threads,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Count threads in a category.
     */
    public function countByCategory(int $categoryId): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.categoryId = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count threads by user.
     */
    public function countByUser(int $userId): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count total threads.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Add vote counts to a collection of threads.
     *
     * @param ForumThread[] $threads
     */
    public function addVoteCounts(array $threads): void
    {
        if (empty($threads)) {
            return;
        }

        $threadIds = array_map(fn(ForumThread $t) => $t->getId(), $threads);
        $voteCounts = $this->voteRepository->getVoteCountsForEntities($threadIds, VoteEntity::FORUM);

        foreach ($threads as $thread) {
            $counts = $voteCounts[$thread->getId()] ?? ['likes' => 0, 'dislikes' => 0];
            $thread->setLikes($counts['likes']);
            $thread->setDislikes($counts['dislikes']);
        }
    }

    /**
     * Add vote counts to a single thread.
     */
    public function addVoteCountsToThread(ForumThread $thread): void
    {
        $counts = $this->voteRepository->getVoteCountsForEntity($thread->getId(), VoteEntity::FORUM);
        $thread->setLikes($counts['likes']);
        $thread->setDislikes($counts['dislikes']);
    }

    public function save(ForumThread $thread, bool $flush = true): ForumThread
    {
        $this->getEntityManager()->persist($thread);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $thread;
    }

    public function remove(ForumThread $thread, bool $flush = true): void
    {
        $this->getEntityManager()->remove($thread);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
