<?php

declare(strict_types=1);

namespace App\Domain\Photo\Repository;

use App\Domain\Photo\Entity\Photo;
use App\Domain\Vote\Enum\VoteEntity;
use App\Domain\Vote\Repository\VoteRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Photo>
 */
class PhotoRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly VoteRepository $voteRepository,
    ) {
        parent::__construct($registry, Photo::class);
    }

    /**
     * Get paginated photo list with vote counts.
     *
     * @return array{items: Photo[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findPaginated(int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->orderBy('p.id', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = count($paginator);

        $photos = iterator_to_array($paginator);
        $this->addVoteCounts($photos);

        return [
            'items' => $photos,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Get paginated photos by user.
     *
     * @return array{items: Photo[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findByUserPaginated(int $userId, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->where('p.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('p.id', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = count($paginator);

        $photos = iterator_to_array($paginator);
        $this->addVoteCounts($photos);

        return [
            'items' => $photos,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Get single photo with user and vote counts.
     */
    public function findWithUser(int $id): ?Photo
    {
        $photo = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if ($photo) {
            $this->addVoteCountsToPhoto($photo);
        }

        return $photo;
    }

    /**
     * Search photos by username.
     *
     * @return array{items: Photo[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function searchByUsername(string $username, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->where('u.username LIKE :username')
            ->setParameter('username', '%' . $username . '%')
            ->orderBy('p.id', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = count($paginator);

        $photos = iterator_to_array($paginator);
        $this->addVoteCounts($photos);

        return [
            'items' => $photos,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Get photos from a specific room.
     *
     * @return array{items: Photo[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function findByRoomPaginated(int $roomId, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->where('p.roomId = :roomId')
            ->setParameter('roomId', $roomId)
            ->orderBy('p.id', 'DESC');

        $qb->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: true);
        $total = count($paginator);

        $photos = iterator_to_array($paginator);
        $this->addVoteCounts($photos);

        return [
            'items' => $photos,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage) ?: 1,
        ];
    }

    /**
     * Get most liked photos.
     *
     * @return Photo[]
     */
    public function findMostLiked(int $limit = 10): array
    {
        // Get photo IDs with most likes
        $conn = $this->getEntityManager()->getConnection();
        $sql = sprintf('
            SELECT p.id, COUNT(v.id) as like_count
            FROM camera_web p
            LEFT JOIN veloce_votes v ON v.entity_id = p.id AND v.vote_entity = :voteEntity AND v.vote_type = 1
            GROUP BY p.id
            ORDER BY like_count DESC
            LIMIT %d
        ', $limit);

        $result = $conn->executeQuery($sql, [
            'voteEntity' => VoteEntity::PHOTO->value,
        ])->fetchAllAssociative();

        $photoIds = array_column($result, 'id');

        if (empty($photoIds)) {
            return [];
        }

        $photos = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $photoIds)
            ->getQuery()
            ->getResult();

        // Sort by original order and add vote counts
        $photosById = [];
        foreach ($photos as $photo) {
            $photosById[$photo->getId()] = $photo;
        }

        $sortedPhotos = [];
        foreach ($photoIds as $id) {
            if (isset($photosById[$id])) {
                $sortedPhotos[] = $photosById[$id];
            }
        }

        $this->addVoteCounts($sortedPhotos);

        return $sortedPhotos;
    }

    /**
     * Count total photos.
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count photos by user.
     */
    public function countByUser(int $userId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Add vote counts to a collection of photos.
     *
     * @param Photo[] $photos
     */
    private function addVoteCounts(array $photos): void
    {
        if (empty($photos)) {
            return;
        }

        $photoIds = array_map(fn(Photo $p) => $p->getId(), $photos);
        $voteCounts = $this->voteRepository->getVoteCountsForEntities($photoIds, VoteEntity::PHOTO);

        foreach ($photos as $photo) {
            $counts = $voteCounts[$photo->getId()] ?? ['likes' => 0, 'dislikes' => 0];
            $photo->setLikes($counts['likes']);
            $photo->setDislikes($counts['dislikes']);
        }
    }

    /**
     * Add vote counts to a single photo.
     */
    private function addVoteCountsToPhoto(Photo $photo): void
    {
        $counts = $this->voteRepository->getVoteCountsForEntity($photo->getId(), VoteEntity::PHOTO);
        $photo->setLikes($counts['likes']);
        $photo->setDislikes($counts['dislikes']);
    }

    public function save(Photo $photo, bool $flush = true): Photo
    {
        $this->getEntityManager()->persist($photo);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $photo;
    }

    public function remove(Photo $photo, bool $flush = true): void
    {
        $this->getEntityManager()->remove($photo);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
