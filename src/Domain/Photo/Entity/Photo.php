<?php

declare(strict_types=1);

namespace App\Domain\Photo\Entity;

use App\Domain\Photo\Repository\PhotoRepository;
use App\Domain\Room\Entity\Room;
use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: PhotoRepository::class)]
#[ORM\Table(name: 'camera_web')]
#[ORM\Index(columns: ['user_id'], name: 'idx_photo_user')]
#[ORM\Index(columns: ['room_id'], name: 'idx_photo_room')]
class Photo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['photo:read', 'photo:list'])]
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
    #[Groups(['photo:read', 'photo:list'])]
    private ?User $user = null {
        get => $this->user;
    }

    #[ORM\Column(name: 'room_id', type: 'integer', options: ['default' => 0])]
    #[Groups(['photo:read', 'photo:list'])]
    public private(set) int $roomId = 0 {
        get => $this->roomId;
    }

    #[ORM\ManyToOne(targetEntity: Room::class)]
    #[ORM\JoinColumn(name: 'room_id', referencedColumnName: 'id', nullable: true)]
    #[Ignore]
    private ?Room $room = null {
        get => $this->room;
    }

    #[ORM\Column(type: 'integer')]
    #[Groups(['photo:read', 'photo:list'])]
    public private(set) int $timestamp {
        get => $this->timestamp;
    }

    #[ORM\Column(length: 255)]
    #[Groups(['photo:read', 'photo:list'])]
    public private(set) string $url {
        get => $this->url;
    }

    // Vote counts (computed, not stored)
    private int $likes = 0;
    private int $dislikes = 0;

    public function __construct()
    {
        $this->timestamp = time();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    #[Groups(['photo:read', 'photo:list'])]
    public function getAuthorName(): ?string
    {
        return $this->user?->username;
    }

    #[Groups(['photo:read', 'photo:list'])]
    public function getAuthorLook(): ?string
    {
        return $this->user?->look;
    }

    #[Groups(['photo:read', 'photo:list'])]
    public function getLikes(): int
    {
        return $this->likes;
    }

    public function setLikes(int $likes): static
    {
        $this->likes = $likes;
        return $this;
    }

    #[Groups(['photo:read', 'photo:list'])]
    public function getDislikes(): int
    {
        return $this->dislikes;
    }

    public function setDislikes(int $dislikes): static
    {
        $this->dislikes = $dislikes;
        return $this;
    }

    #[Groups(['photo:read', 'photo:list'])]
    public function getTakenAt(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->setTimestamp($this->timestamp);
    }

    public function hasRoom(): bool
    {
        return $this->roomId > 0;
    }
}
