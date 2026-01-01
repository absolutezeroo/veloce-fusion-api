<?php

declare(strict_types=1);

namespace App\Domain\Article\Repository;

use App\Domain\Article\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    public function findBySlug(string $slug): ?Tag
    {
        return $this->findOneBy(['slug' => strtolower($slug)]);
    }

    public function findByName(string $name): ?Tag
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Find or create a tag by name.
     */
    public function findOrCreate(string $name): Tag
    {
        $tag = $this->findByName($name);

        if ($tag === null) {
            $tag = Tag::create($name);
            $this->save($tag);
        }

        return $tag;
    }

    /**
     * @return Tag[]
     */
    public function findPopular(int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.usageCount > 0')
            ->orderBy('t.usageCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param string[] $names
     * @return Tag[]
     */
    public function findByNames(array $names): array
    {
        if (empty($names)) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->where('t.name IN (:names)')
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Tag[]
     */
    public function searchByName(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.usageCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(Tag $tag, bool $flush = true): Tag
    {
        $this->getEntityManager()->persist($tag);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $tag;
    }

    public function remove(Tag $tag, bool $flush = true): void
    {
        $this->getEntityManager()->remove($tag);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
