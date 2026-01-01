<?php

declare(strict_types=1);

namespace App\Domain\Setting\Repository;

use App\Domain\Setting\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    public function findByKey(string $key): ?Setting
    {
        return $this->findOneBy(['key' => $key]);
    }

    public function getValue(string $key, ?string $default = null): ?string
    {
        $setting = $this->findByKey($key);

        return $setting !== null ? $setting->value : $default;
    }

    /**
     * @return array{items: Setting[], total: int, page: int, perPage: int, lastPage: int}
     */
    public function getPaginated(int $page = 1, int $perPage = 20): array
    {
        $query = $this->createQueryBuilder('s')
            ->orderBy('s.key', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery();

        $paginator = new Paginator($query);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'lastPage' => (int) ceil($total / $perPage),
        ];
    }

    public function save(Setting $setting, bool $flush = true): Setting
    {
        $this->getEntityManager()->persist($setting);

        if ($flush) {
            $this->getEntityManager()->flush();
        }

        return $setting;
    }
}
