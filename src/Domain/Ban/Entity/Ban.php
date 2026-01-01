<?php

declare(strict_types=1);

namespace App\Domain\Ban\Entity;

use App\Domain\Ban\Enum\BanType;
use App\Domain\Ban\Repository\BanRepository;
use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: BanRepository::class)]
#[ORM\Table(name: 'bans')]
#[ORM\Index(columns: ['user_id'], name: 'idx_ban_user')]
#[ORM\Index(columns: ['ip'], name: 'idx_ban_ip')]
#[ORM\Index(columns: ['machine_id'], name: 'idx_ban_machine')]
class Ban
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['ban:read', 'ban:list'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(name: 'user_id', type: 'integer')]
    #[Groups(['ban:read', 'ban:list'])]
    public private(set) int $userId {
        get => $this->userId;
    }

    #[ORM\Column(length: 45)]
    #[Groups(['ban:read'])]
    public private(set) string $ip {
        get => $this->ip;
        set => trim($value);
    }

    #[ORM\Column(name: 'machine_id', length: 255)]
    #[Groups(['ban:read'])]
    public private(set) string $machineId {
        get => $this->machineId;
        set => trim($value);
    }

    #[ORM\Column(name: 'user_staff_id', type: 'integer')]
    #[Groups(['ban:read', 'ban:list'])]
    public private(set) int $staffUserId {
        get => $this->staffUserId;
    }

    #[ORM\Column(name: 'timestamp', type: 'integer')]
    #[Groups(['ban:read', 'ban:list'])]
    public private(set) int $createdAt {
        get => $this->createdAt;
    }

    #[ORM\Column(name: 'ban_expire', type: 'integer')]
    #[Groups(['ban:read', 'ban:list'])]
    public private(set) int $expiresAt {
        get => $this->expiresAt;
    }

    #[ORM\Column(name: 'ban_reason', type: 'text')]
    #[Groups(['ban:read', 'ban:list'])]
    public private(set) string $reason {
        get => $this->reason;
        set => trim($value);
    }

    #[ORM\Column(length: 32)]
    #[Groups(['ban:read', 'ban:list'])]
    public private(set) string $type {
        get => $this->type;
    }

    #[ORM\Column(name: 'cfh_topic', type: 'integer', options: ['default' => -1])]
    #[Ignore]
    public private(set) int $cfhTopic = -1 {
        get => $this->cfhTopic;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Ignore]
    private ?User $bannedUser = null {
        get => $this->bannedUser;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_staff_id', referencedColumnName: 'id', nullable: false)]
    #[Ignore]
    private ?User $staffUser = null {
        get => $this->staffUser;
    }

    public function __construct()
    {
        $this->createdAt = time();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getBannedUser(): ?User
    {
        return $this->bannedUser;
    }

    public function getStaffUser(): ?User
    {
        return $this->staffUser;
    }

    public function isActive(): bool
    {
        // expiresAt = 0 means permanent ban
        if ($this->expiresAt === 0) {
            return true;
        }

        return $this->expiresAt > time();
    }

    public function isPermanent(): bool
    {
        return $this->expiresAt === 0;
    }

    public function getRemainingTime(): ?int
    {
        if ($this->isPermanent()) {
            return null;
        }

        $remaining = $this->expiresAt - time();

        return max(0, $remaining);
    }

    public function getBanType(): BanType
    {
        return BanType::tryFrom($this->type) ?? BanType::USER;
    }

    public static function create(
        int $userId,
        int $staffUserId,
        string $reason,
        BanType $type,
        int $expiresAt = 0,
        string $ip = '',
        string $machineId = '',
    ): self {
        $ban = new self();
        $ban->userId = $userId;
        $ban->staffUserId = $staffUserId;
        $ban->reason = $reason;
        $ban->type = $type->value;
        $ban->expiresAt = $expiresAt;
        $ban->ip = $ip;
        $ban->machineId = $machineId;

        return $ban;
    }
}
