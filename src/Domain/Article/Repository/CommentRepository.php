<?php

declare(strict_types=1);

namespace App\Domain\Article\Repository;

use App\Domain\Article\Entity\Comment;
use App\Domain\Article\Enum\CommentStatus;
use App\Domain\Vote\Enum\VoteEntity;
use App\Domain\Vote\Repository\VoteRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly VoteRepository $voteRepository,
    ) {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Get approved comments for an article (public view).
     *
     * @return array{items: Comment[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findApprovedByArticle(int $articleId, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->addSelect('u')
            ->where('c.articleId = :articleId')
            ->andWhere('c.status = :status')
            ->andWhere('c.parentId IS NULL') // Only root comments
            ->setParameter('articleId', $articleId)
            ->setParameter('status', CommentStatus::APPROVED)
            ->orderBy('c.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb);
        $total = count($paginator);

        $comments = iterator_to_array($paginator);
        $this->addVoteCounts($comments);

        return [
            'items' => $comments,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Get replies for a comment.
     *
     * @return Comment[]
     */
    public function findApprovedReplies(int $parentId): array
    {
        $comments = $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->addSelect('u')
            ->where('c.parentId = :parentId')
            ->andWhere('c.status = :status')
            ->setParameter('parentId', $parentId)
            ->setParameter('status', CommentStatus::APPROVED)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $this->addVoteCounts($comments);

        return $comments;
    }

    /**
     * Get all comments for moderation (admin).
     *
     * @return array{items: Comment[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findForModeration(
        int $page = 1,
        int $perPage = 20,
        ?CommentStatus $status = null,
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.article', 'a')
            ->addSelect('u', 'a')
            ->orderBy('c.createdAt', 'DESC');

        if ($status !== null) {
            $qb->where('c.status = :status')
                ->setParameter('status', $status);
        }

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Count pending comments (for admin badge).
     */
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.status = :status')
            ->setParameter('status', CommentStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count approved comments for an article.
     */
    public function countApprovedByArticle(int $articleId): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.articleId = :articleId')
            ->andWhere('c.status = :status')
            ->setParameter('articleId', $articleId)
            ->setParameter('status', CommentStatus::APPROVED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get comments by user.
     *
     * @return Comment[]
     */
    public function findByUser(int $userId, int $limit = 20): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.article', 'a')
            ->addSelect('a')
            ->where('c.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(Comment $comment, bool $flush = true): Comment
    {
        $this->getEntityManager()->persist($comment);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $comment;
    }

    public function remove(Comment $comment, bool $flush = true): void
    {
        $this->getEntityManager()->remove($comment);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    /**
     * Add vote counts to a collection of comments.
     *
     * @param Comment[] $comments
     */
    public function addVoteCounts(array $comments): void
    {
        if (empty($comments)) {
            return;
        }

        $commentIds = array_map(fn(Comment $c) => $c->getId(), $comments);
        $voteCounts = $this->voteRepository->getVoteCountsForEntities($commentIds, VoteEntity::ARTICLE_COMMENT);

        foreach ($comments as $comment) {
            $counts = $voteCounts[$comment->getId()] ?? ['likes' => 0, 'dislikes' => 0];
            $comment->setLikes($counts['likes']);
            $comment->setDislikes($counts['dislikes']);
        }
    }

    /**
     * Add vote counts to a single comment.
     */
    public function addVoteCountsToComment(Comment $comment): void
    {
        $counts = $this->voteRepository->getVoteCountsForEntity($comment->getId(), VoteEntity::ARTICLE_COMMENT);
        $comment->setLikes($counts['likes']);
        $comment->setDislikes($counts['dislikes']);
    }
}
