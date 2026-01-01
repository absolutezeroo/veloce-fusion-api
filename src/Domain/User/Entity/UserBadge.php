<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use App\Domain\User\Repository\UserBadgeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: UserBadgeRepository::class)]
#[ORM\Table(name: 'users_badges')]
#[ORM\Index(columns: ['user_id'], name: 'idx_userbadge_user')]
#[ORM\Index(columns: ['slot_id'], name: 'idx_userbadge_slot')]
class UserBadge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['badge:read', 'badge:list'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(name: 'user_id', type: 'integer')]
    #[Ignore]
    public private(set) int $userId {
        get => $this->userId;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Ignore]
    private ?User $user = null {
        get => $this->user;
    }

    #[ORM\Column(name: 'slot_id', type: 'integer', options: ['default' => 0])]
    #[Groups(['badge:read', 'badge:list', 'badge:slot'])]
    public private(set) int $slotId = 0 {
        get => $this->slotId;
    }

    #[ORM\Column(name: 'badge_code', length: 50)]
    #[Groups(['badge:read', 'badge:list', 'badge:slot'])]
    public private(set) string $badgeCode {
        get => $this->badgeCode;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    /**
     * Check if badge is slotted (visible on profile).
     */
    #[Groups(['badge:read', 'badge:list'])]
    public function isSlotted(): bool
    {
        return $this->slotId > 0;
    }

    /**
     * Set the slot for this badge.
     */
    public function setSlot(int $slotId): static
    {
        $this->slotId = $slotId;
        return $this;
    }

    /**
     * Remove badge from slot.
     */
    public function unslot(): static
    {
        $this->slotId = 0;
        return $this;
    }

    public static function create(int $userId, string $badgeCode, int $slotId = 0): self
    {
        $badge = new self();
        $badge->userId = $userId;
        $badge->badgeCode = $badgeCode;
        $badge->slotId = $slotId;

        return $badge;
    }
}
