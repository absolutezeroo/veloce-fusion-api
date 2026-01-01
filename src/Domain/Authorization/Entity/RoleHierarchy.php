<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Entity;

use App\Domain\Authorization\Repository\RoleHierarchyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Defines role inheritance: a parent role inherits permissions from child roles.
 * Example: "Admin" (parent) inherits from "Moderator" (child).
 */
#[ORM\Entity(repositoryClass: RoleHierarchyRepository::class)]
#[ORM\Table(name: 'veloce_roles_hierarchy')]
#[ORM\UniqueConstraint(name: 'unique_hierarchy', columns: ['parent_role_id', 'child_role_id'])]
class RoleHierarchy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['role_hierarchy:read'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(name: 'parent_role_id', type: 'integer')]
    #[Groups(['role_hierarchy:read'])]
    public private(set) int $parentRoleId {
        get => $this->parentRoleId;
    }

    #[ORM\Column(name: 'child_role_id', type: 'integer')]
    #[Groups(['role_hierarchy:read'])]
    public private(set) int $childRoleId {
        get => $this->childRoleId;
    }

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    #[Groups(['role_hierarchy:read'])]
    public private(set) \DateTimeImmutable $createdAt {
        get => $this->createdAt;
    }

    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(name: 'parent_role_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Role $parentRole = null {
        get => $this->parentRole;
    }

    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(name: 'child_role_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Role $childRole = null {
        get => $this->childRole;
    }

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getParentRole(): ?Role
    {
        return $this->parentRole;
    }

    public function getChildRole(): ?Role
    {
        return $this->childRole;
    }

    public static function create(int $parentRoleId, int $childRoleId): self
    {
        $rh = new self();
        $rh->parentRoleId = $parentRoleId;
        $rh->childRoleId = $childRoleId;

        return $rh;
    }
}
