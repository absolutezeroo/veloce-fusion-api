<?php

declare(strict_types=1);

namespace App\Domain\Article\Repository;

use App\Domain\Article\Entity\Article;
use App\Domain\Article\Enum\ArticleStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function findBySlug(string $slug): ?Article
    {
        return $this->findOneBy(['slug' => strtolower($slug)]);
    }

    /**
     * Get published article by slug (for public view).
     */
    public function findPublishedBySlug(string $slug): ?Article
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.author', 'u')
            ->leftJoin('a.category', 'c')
            ->addSelect('u', 'c')
            ->where('a.slug = :slug')
            ->andWhere('a.status = :status')
            ->setParameter('slug', strtolower($slug))
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get paginated published articles (public listing).
     *
     * @return array{items: Article[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findPublishedPaginated(int $page = 1, int $perPage = 10, ?int $categoryId = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.author', 'u')
            ->leftJoin('a.category', 'c')
            ->addSelect('u', 'c')
            ->where('a.status = :status')
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->orderBy('a.isPinned', 'DESC')
            ->addOrderBy('a.publishedAt', 'DESC');

        if ($categoryId !== null) {
            $qb->andWhere('a.categoryId = :categoryId')
                ->setParameter('categoryId', $categoryId);
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
     * Get paginated articles for admin (all statuses).
     *
     * @return array{items: Article[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findAllPaginated(
        int $page = 1,
        int $perPage = 20,
        ?ArticleStatus $status = null,
        ?int $categoryId = null,
        ?int $authorId = null,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.author', 'u')
            ->leftJoin('a.category', 'c')
            ->addSelect('u', 'c')
            ->orderBy('a.createdAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        if ($categoryId !== null) {
            $qb->andWhere('a.categoryId = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        if ($authorId !== null) {
            $qb->andWhere('a.authorId = :authorId')
                ->setParameter('authorId', $authorId);
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
     * Get pinned published articles.
     *
     * @return Article[]
     */
    public function findPinned(int $limit = 5): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.author', 'u')
            ->leftJoin('a.category', 'c')
            ->addSelect('u', 'c')
            ->where('a.status = :status')
            ->andWhere('a.isPinned = true')
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get featured published articles.
     *
     * @return Article[]
     */
    public function findFeatured(int $limit = 3): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.author', 'u')
            ->leftJoin('a.category', 'c')
            ->addSelect('u', 'c')
            ->where('a.status = :status')
            ->andWhere('a.isFeatured = true')
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get articles by tag slug.
     *
     * @return array{items: Article[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findByTagSlug(string $tagSlug, int $page = 1, int $perPage = 10): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.author', 'u')
            ->leftJoin('a.category', 'c')
            ->leftJoin('a.tags', 't')
            ->addSelect('u', 'c')
            ->where('a.status = :status')
            ->andWhere('t.slug = :tagSlug')
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->setParameter('tagSlug', strtolower($tagSlug))
            ->orderBy('a.publishedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
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
     * Find articles scheduled for publishing.
     *
     * @return Article[]
     */
    public function findScheduledToPublish(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.status = :status')
            ->andWhere('a.publishedAt <= :now')
            ->setParameter('status', ArticleStatus::SCHEDULED)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Search articles by title or content.
     *
     * @return array{items: Article[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function search(string $query, int $page = 1, int $perPage = 10): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.author', 'u')
            ->leftJoin('a.category', 'c')
            ->addSelect('u', 'c')
            ->where('a.status = :status')
            ->andWhere('(a.title LIKE :query OR a.description LIKE :query OR a.content LIKE :query)')
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('a.publishedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
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

    public function save(Article $article, bool $flush = true): Article
    {
        $this->getEntityManager()->persist($article);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $article;
    }

    public function remove(Article $article, bool $flush = true): void
    {
        $this->getEntityManager()->remove($article);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
