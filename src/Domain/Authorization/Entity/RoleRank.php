<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Entity;

use App\Domain\Authorization\Repository\RoleRankRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Links a Role to a User Rank (level).
 * Users with a specific rank will inherit all permissions from linked roles.
 */
#[ORM\Entity(repositoryClass: RoleRankRepository::class)]
#[ORM\Table(name: 'veloce_roles_rank')]
#[ORM\UniqueConstraint(name: 'unique_role_rank', columns: ['role_id', 'rank_id'])]
class RoleRank
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['role_rank:read'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(name: 'role_id', type: 'integer')]
    #[Groups(['role_rank:read'])]
    public private(set) int $roleId {
        get => $this->roleId;
    }

    /**
     * The rank level (from User entity rank field).
     * Typically: 1=User, 2-6=Staff levels, 7=Admin
     */
    #[ORM\Column(name: 'rank_id', type: 'integer')]
    #[Groups(['role_rank:read'])]
    public private(set) int $rankId {
        get => $this->rankId;
    }

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    #[Groups(['role_rank:read'])]
    public private(set) \DateTimeImmutable $createdAt {
        get => $this->createdAt;
    }

    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'roleRanks')]
    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Role $role = null {
        get => $this->role;
    }

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public static function create(int $roleId, int $rankId): self
    {
        $rr = new self();
        $rr->roleId = $roleId;
        $rr->rankId = $rankId;

        return $rr;
    }
}
