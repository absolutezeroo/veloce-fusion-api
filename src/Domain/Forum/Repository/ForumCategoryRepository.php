<?php

declare(strict_types=1);

namespace App\Domain\Forum\Repository;

use App\Domain\Forum\Entity\ForumCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ForumCategory>
 */
class ForumCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumCategory::class);
    }

    /**
     * Find category by slug.
     */
    public function findBySlug(string $slug): ?ForumCategory
    {
        return $this->findOneBy(['slug' => strtolower($slug)]);
    }

    /**
     * Get all root categories (no parent) with children.
     *
     * @return ForumCategory[]
     */
    public function findRootCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.children', 'ch')
            ->addSelect('ch')
            ->where('c.parentId IS NULL')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all categories as flat list.
     *
     * @return ForumCategory[]
     */
    public function findAll(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get category with its children.
     */
    public function findWithChildren(int $id): ?ForumCategory
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.children', 'ch')
            ->addSelect('ch')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get category with parent info.
     */
    public function findWithParent(int $id): ?ForumCategory
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.parent', 'p')
            ->addSelect('p')
            ->where('c.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get categories for dropdown (id => name with hierarchy).
     *
     * @return array<int, string>
     */
    public function findForDropdown(): array
    {
        $categories = $this->findRootCategories();
        $result = [];

        foreach ($categories as $category) {
            $result[$category->getId()] = $category->name;

            foreach ($category->getChildren() as $child) {
                $result[$child->getId()] = '— ' . $child->name;
            }
        }

        return $result;
    }

    /**
     * Count all categories.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get next position for root or child category.
     */
    public function getNextPosition(?int $parentId = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('MAX(c.position)');

        if ($parentId === null) {
            $qb->where('c.parentId IS NULL');
        } else {
            $qb->where('c.parentId = :parentId')
                ->setParameter('parentId', $parentId);
        }

        $max = $qb->getQuery()->getSingleScalarResult();

        return $max !== null ? ((int) $max + 1) : 0;
    }

    public function save(ForumCategory $category, bool $flush = true): ForumCategory
    {
        $this->getEntityManager()->persist($category);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $category;
    }

    public function remove(ForumCategory $category, bool $flush = true): void
    {
        $this->getEntityManager()->remove($category);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
