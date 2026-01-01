<?php

declare(strict_types=1);

namespace App\Domain\Guild\Entity;

use App\Domain\Guild\Repository\GuildRepository;
use App\Domain\Room\Entity\Room;
use App\Domain\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: GuildRepository::class)]
#[ORM\Table(name: 'guilds')]
#[ORM\Index(columns: ['user_id'], name: 'idx_guild_owner')]
#[ORM\Index(columns: ['room_id'], name: 'idx_guild_room')]
class Guild
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['guild:read', 'guild:list', 'guild:search'])]
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
    #[Groups(['guild:read', 'guild:list'])]
    private ?User $owner = null {
        get => $this->owner;
    }

    #[ORM\Column(length: 50)]
    #[Groups(['guild:read', 'guild:list', 'guild:search'])]
    public private(set) string $name {
        get => $this->name;
        set => trim($value);
    }

    #[ORM\Column(type: 'text')]
    #[Groups(['guild:read', 'guild:list'])]
    public private(set) string $description = '' {
        get => $this->description;
    }

    #[ORM\Column(name: 'room_id', type: 'integer', options: ['default' => 0])]
    #[Ignore]
    public private(set) int $roomId = 0 {
        get => $this->roomId;
    }

    #[ORM\ManyToOne(targetEntity: Room::class)]
    #[ORM\JoinColumn(name: 'room_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['guild:read'])]
    private ?Room $room = null {
        get => $this->room;
    }

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['guild:read'])]
    public private(set) int $state = 0 {
        get => $this->state;
    }

    #[ORM\Column(length: 100)]
    #[Groups(['guild:read', 'guild:list', 'guild:search'])]
    public private(set) string $badge = '' {
        get => $this->badge;
    }

    #[ORM\Column(name: 'date_created', type: 'integer')]
    #[Groups(['guild:read'])]
    public private(set) int $dateCreated {
        get => $this->dateCreated;
    }

    /** @var Collection<int, GuildMember> */
    #[ORM\OneToMany(targetEntity: GuildMember::class, mappedBy: 'guild')]
    #[Ignore]
    private Collection $members {
        get => $this->members;
    }

    private ?int $memberCount = null;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->dateCreated = time();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    /**
     * @return Collection<int, GuildMember>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    #[Groups(['guild:read', 'guild:list', 'guild:search'])]
    public function getMemberCount(): int
    {
        return $this->memberCount ?? $this->members->count();
    }

    public function setMemberCount(int $count): static
    {
        $this->memberCount = $count;
        return $this;
    }

    public function isOwner(User $user): bool
    {
        return $this->userId === $user->getId();
    }

    public function hasRoom(): bool
    {
        return $this->roomId > 0;
    }

    #[Groups(['guild:read'])]
    public function getCreatedAt(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->setTimestamp($this->dateCreated);
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

    public function updateBadge(string $badge): static
    {
        $this->badge = $badge;
        return $this;
    }
}
