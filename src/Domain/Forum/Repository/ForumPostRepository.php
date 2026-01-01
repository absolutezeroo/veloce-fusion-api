<?php

declare(strict_types=1);

namespace App\Domain\Forum\Repository;

use App\Domain\Forum\Entity\ForumPost;
use App\Domain\Forum\Enum\PostStatus;
use App\Domain\Vote\Enum\VoteEntity;
use App\Domain\Vote\Repository\VoteRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumPost>
 */
class ForumPostRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly VoteRepository $voteRepository,
    ) {
        parent::__construct($registry, ForumPost::class);
    }

    /**
     * Get paginated posts for a thread (approved only for public).
     *
     * @return array{items: ForumPost[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findByThreadPaginated(int $threadId, int $page = 1, int $perPage = 20, bool $includeHidden = false): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->leftJoin('p.quotedPost', 'qp')
            ->leftJoin('qp.user', 'qpu')
            ->addSelect('u', 'qp', 'qpu')
            ->where('p.threadId = :threadId')
            ->setParameter('threadId', $threadId)
            ->orderBy('p.createdAt', 'ASC');

        if (!$includeHidden) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', PostStatus::APPROVED);
        }

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = count($paginator);

        $posts = iterator_to_array($paginator);
        $this->addVoteCounts($posts);

        return [
            'items' => $posts,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Get posts by user.
     *
     * @return array{items: ForumPost[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findByUserPaginated(int $userId, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.thread', 't')
            ->leftJoin('t.category', 'c')
            ->addSelect('t', 'c')
            ->where('p.userId = :userId')
            ->andWhere('p.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', PostStatus::APPROVED)
            ->orderBy('p.createdAt', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = count($paginator);

        $posts = iterator_to_array($paginator);
        $this->addVoteCounts($posts);

        return [
            'items' => $posts,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Get posts pending moderation.
     *
     * @return array{items: ForumPost[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findPendingPaginated(int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->leftJoin('p.thread', 't')
            ->addSelect('u', 't')
            ->where('p.status = :status')
            ->setParameter('status', PostStatus::PENDING)
            ->orderBy('p.createdAt', 'ASC');

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
     * Get single post with details.
     */
    public function findWithDetails(int $id): ?ForumPost
    {
        $post = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->leftJoin('p.thread', 't')
            ->leftJoin('p.quotedPost', 'qp')
            ->leftJoin('qp.user', 'qpu')
            ->addSelect('u', 't', 'qp', 'qpu')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($post) {
            $this->addVoteCountsToPost($post);
        }

        return $post;
    }

    /**
     * Get last post in a thread.
     */
    public function findLastInThread(int $threadId): ?ForumPost
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->where('p.threadId = :threadId')
            ->andWhere('p.status = :status')
            ->setParameter('threadId', $threadId)
            ->setParameter('status', PostStatus::APPROVED)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count posts in a thread (approved only).
     */
    public function countByThread(int $threadId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.threadId = :threadId')
            ->andWhere('p.status = :status')
            ->setParameter('threadId', $threadId)
            ->setParameter('status', PostStatus::APPROVED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count posts by user.
     */
    public function countByUser(int $userId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.userId = :userId')
            ->andWhere('p.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', PostStatus::APPROVED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count pending posts.
     */
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', PostStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count total posts.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', PostStatus::APPROVED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Search posts by content.
     *
     * @return array{items: ForumPost[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function search(string $query, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->leftJoin('p.thread', 't')
            ->addSelect('u', 't')
            ->where('p.content LIKE :query')
            ->andWhere('p.status = :status')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('status', PostStatus::APPROVED)
            ->orderBy('p.createdAt', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = count($paginator);

        $posts = iterator_to_array($paginator);
        $this->addVoteCounts($posts);

        return [
            'items' => $posts,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Add vote counts to a collection of posts.
     *
     * @param ForumPost[] $posts
     */
    public function addVoteCounts(array $posts): void
    {
        if (empty($posts)) {
            return;
        }

        $postIds = array_map(fn(ForumPost $p) => $p->getId(), $posts);
        $voteCounts = $this->voteRepository->getVoteCountsForEntities($postIds, VoteEntity::FORUM_COMMENT);

        foreach ($posts as $post) {
            $counts = $voteCounts[$post->getId()] ?? ['likes' => 0, 'dislikes' => 0];
            $post->setLikes($counts['likes']);
            $post->setDislikes($counts['dislikes']);
        }
    }

    /**
     * Add vote counts to a single post.
     */
    public function addVoteCountsToPost(ForumPost $post): void
    {
        $counts = $this->voteRepository->getVoteCountsForEntity($post->getId(), VoteEntity::FORUM_COMMENT);
        $post->setLikes($counts['likes']);
        $post->setDislikes($counts['dislikes']);
    }

    public function save(ForumPost $post, bool $flush = true): ForumPost
    {
        $this->getEntityManager()->persist($post);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $post;
    }

    public function remove(ForumPost $post, bool $flush = true): void
    {
        $this->getEntityManager()->remove($post);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
