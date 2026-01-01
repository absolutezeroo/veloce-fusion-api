<?php

declare(strict_types=1);

namespace App\Domain\Guild\Entity;

use App\Domain\Guild\Repository\GuildMemberRepository;
use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: GuildMemberRepository::class)]
#[ORM\Table(name: 'guilds_members')]
#[ORM\Index(columns: ['guild_id'], name: 'idx_guild_member_guild')]
#[ORM\Index(columns: ['user_id'], name: 'idx_guild_member_user')]
#[ORM\UniqueConstraint(name: 'unique_guild_user', columns: ['guild_id', 'user_id'])]
class GuildMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['member:read', 'member:list'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(name: 'guild_id', type: 'integer')]
    #[Groups(['member:read'])]
    public private(set) int $guildId {
        get => $this->guildId;
    }

    #[ORM\ManyToOne(targetEntity: Guild::class, inversedBy: 'members')]
    #[ORM\JoinColumn(name: 'guild_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Ignore]
    private ?Guild $guild = null {
        get => $this->guild;
    }

    #[ORM\Column(name: 'user_id', type: 'integer')]
    #[Ignore]
    public private(set) int $userId {
        get => $this->userId;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['member:read', 'member:list'])]
    private ?User $user = null {
        get => $this->user;
    }

    #[ORM\Column(name: 'level_id', type: 'integer', options: ['default' => 3])]
    #[Groups(['member:read', 'member:list'])]
    public private(set) int $levelId = 3 {
        get => $this->levelId;
    }

    #[ORM\Column(name: 'member_since', type: 'integer')]
    #[Groups(['member:read', 'member:list'])]
    public private(set) int $memberSince {
        get => $this->memberSince;
    }

    public function __construct()
    {
        $this->memberSince = time();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getGuild(): ?Guild
    {
        return $this->guild;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    #[Groups(['member:read', 'member:list'])]
    public function getJoinedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable()->setTimestamp($this->memberSince);
    }

    /**
     * Check if member is owner (level 0).
     */
    public function isOwner(): bool
    {
        return $this->levelId === 0;
    }

    /**
     * Check if member is admin (level 1).
     */
    public function isAdmin(): bool
    {
        return $this->levelId <= 1;
    }

    /**
     * Check if member has rights (level <= 2).
     */
    public function hasRights(): bool
    {
        return $this->levelId <= 2;
    }

    #[Groups(['member:read', 'member:list'])]
    public function getRank(): string
    {
        return match ($this->levelId) {
            0 => 'owner',
            1 => 'admin',
            2 => 'rights',
            default => 'member',
        };
    }

    public function promote(): static
    {
        if ($this->levelId > 1) {
            $this->levelId--;
        }
        return $this;
    }

    public function demote(): static
    {
        if ($this->levelId < 3) {
            $this->levelId++;
        }
        return $this;
    }

    public static function create(int $guildId, int $userId, int $levelId = 3): self
    {
        $member = new self();
        $member->guildId = $guildId;
        $member->userId = $userId;
        $member->levelId = $levelId;
        return $member;
    }
}
