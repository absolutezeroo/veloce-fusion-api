<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Entity;

use App\Domain\Authorization\Repository\RolePermissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: RolePermissionRepository::class)]
#[ORM\Table(name: 'veloce_roles_permission')]
#[ORM\UniqueConstraint(name: 'unique_role_permission', columns: ['role_id', 'permission_id'])]
class RolePermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['role_permission:read'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(name: 'role_id', type: 'integer')]
    #[Groups(['role_permission:read'])]
    public private(set) int $roleId {
        get => $this->roleId;
    }

    #[ORM\Column(name: 'permission_id', type: 'integer')]
    #[Groups(['role_permission:read'])]
    public private(set) int $permissionId {
        get => $this->permissionId;
    }

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    #[Groups(['role_permission:read'])]
    public private(set) \DateTimeImmutable $createdAt {
        get => $this->createdAt;
    }

    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'rolePermissions')]
    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Role $role = null {
        get => $this->role;
    }

    #[ORM\ManyToOne(targetEntity: Permission::class, inversedBy: 'rolePermissions')]
    #[ORM\JoinColumn(name: 'permission_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Permission $permission = null {
        get => $this->permission;
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

    public function getPermission(): ?Permission
    {
        return $this->permission;
    }

    public static function create(int $roleId, int $permissionId): self
    {
        $rp = new self();
        $rp->roleId = $roleId;
        $rp->permissionId = $permissionId;

        return $rp;
    }
}
