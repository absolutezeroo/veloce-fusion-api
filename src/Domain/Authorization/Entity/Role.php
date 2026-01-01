<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Entity;

use App\Domain\Authorization\Repository\RoleRepository;
use App\Domain\Shared\Entity\TimestampableTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'veloce_roles')]
#[ORM\HasLifecycleCallbacks]
class Role
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['role:read', 'role:list'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(length: 255, unique: true)]
    #[Groups(['role:read', 'role:list'])]
    public private(set) string $name {
        get => $this->name;
        set => strtolower(trim($value));
    }

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['role:read'])]
    public private(set) ?string $description = null {
        get => $this->description;
    }

    #[ORM\Column(type: 'smallint', options: ['default' => 1])]
    #[Groups(['role:read'])]
    public private(set) int $status = 1 {
        get => $this->status;
    }

    /** @var Collection<int, RolePermission> */
    #[ORM\OneToMany(targetEntity: RolePermission::class, mappedBy: 'role', cascade: ['remove'])]
    #[Ignore]
    private Collection $rolePermissions {
        get => $this->rolePermissions;
    }

    /** @var Collection<int, RoleRank> */
    #[ORM\OneToMany(targetEntity: RoleRank::class, mappedBy: 'role', cascade: ['remove'])]
    #[Ignore]
    private Collection $roleRanks {
        get => $this->roleRanks;
    }

    public function __construct()
    {
        $this->rolePermissions = new ArrayCollection();
        $this->roleRanks = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function isActive(): bool
    {
        return $this->status === 1;
    }

    public function activate(): static
    {
        $this->status = 1;
        return $this;
    }

    public function deactivate(): static
    {
        $this->status = 0;
        return $this;
    }

    public function updateName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function updateDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public static function create(string $name, ?string $description = null): self
    {
        $role = new self();
        $role->name = $name;
        $role->description = $description;

        return $role;
    }
}
