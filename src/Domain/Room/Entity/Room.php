<?php

declare(strict_types=1);

namespace App\Domain\Room\Entity;

use App\Domain\Guild\Entity\Guild;
use App\Domain\Room\Enum\RoomState;
use App\Domain\Room\Repository\RoomRepository;
use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ORM\Table(name: 'rooms')]
#[ORM\Index(columns: ['owner_id'], name: 'idx_room_owner')]
#[ORM\Index(columns: ['guild_id'], name: 'idx_room_guild')]
class Room
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['room:read', 'room:list'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(name: 'owner_id', type: 'integer')]
    #[Ignore]
    public private(set) int $ownerId {
        get => $this->ownerId;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['room:read', 'room:list'])]
    private ?User $owner = null {
        get => $this->owner;
    }

    #[ORM\Column(length: 75)]
    #[Groups(['room:read', 'room:list', 'room:search'])]
    public private(set) string $name {
        get => $this->name;
        set => trim($value);
    }

    #[ORM\Column(type: 'text')]
    #[Groups(['room:read', 'room:list'])]
    public private(set) string $description = '' {
        get => $this->description;
    }

    #[ORM\Column(length: 15, enumType: RoomState::class)]
    #[Groups(['room:read', 'room:list'])]
    public private(set) RoomState $state = RoomState::OPEN {
        get => $this->state;
    }

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['room:read', 'room:list', 'room:search'])]
    public private(set) int $users = 0 {
        get => $this->users;
    }

    #[ORM\Column(name: 'users_max', type: 'integer', options: ['default' => 25])]
    #[Groups(['room:read', 'room:list', 'room:search'])]
    public private(set) int $usersMax = 25 {
        get => $this->usersMax;
    }

    #[ORM\Column(name: 'guild_id', type: 'integer', options: ['default' => 0])]
    #[Ignore]
    public private(set) int $guildId = 0 {
        get => $this->guildId;
    }

    #[ORM\ManyToOne(targetEntity: Guild::class)]
    #[ORM\JoinColumn(name: 'guild_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['room:read'])]
    private ?Guild $guild = null {
        get => $this->guild;
    }

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['room:read', 'room:list'])]
    public private(set) int $score = 0 {
        get => $this->score;
    }

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['room:read'])]
    public private(set) ?string $password = null {
        get => $this->password;
    }

    #[ORM\Column(length: 15, options: ['default' => 'model_a'])]
    #[Groups(['room:read'])]
    public private(set) string $model = 'model_a' {
        get => $this->model;
    }

    #[ORM\Column(name: 'category', type: 'integer', options: ['default' => 0])]
    #[Groups(['room:read'])]
    public private(set) int $categoryId = 0 {
        get => $this->categoryId;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function getGuild(): ?Guild
    {
        return $this->guild;
    }

    #[Groups(['room:list', 'room:search'])]
    public function getOwnerName(): ?string
    {
        return $this->owner?->username;
    }

    public function isOwner(User $user): bool
    {
        return $this->ownerId === $user->getId();
    }

    public function hasGuild(): bool
    {
        return $this->guildId > 0 && $this->guild !== null;
    }

    #[Groups(['room:list'])]
    public function getGuildBadge(): ?string
    {
        return $this->guild?->badge;
    }

    public function isFull(): bool
    {
        return $this->users >= $this->usersMax;
    }

    public function getOccupancyPercent(): int
    {
        if ($this->usersMax === 0) {
            return 0;
        }
        return (int) round(($this->users / $this->usersMax) * 100);
    }

    public function updateName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function updateDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function updateState(RoomState $state): static
    {
        $this->state = $state;
        return $this;
    }

    public function updatePassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function updateMaxUsers(int $max): static
    {
        $this->usersMax = max(1, min(100, $max));
        return $this;
    }
}
