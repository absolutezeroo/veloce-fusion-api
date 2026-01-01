<?php

declare(strict_types=1);

namespace App\Domain\Vote\Entity;

use App\Domain\User\Entity\User;
use App\Domain\Vote\Enum\VoteEntity;
use App\Domain\Vote\Enum\VoteType;
use App\Domain\Vote\Repository\VoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: VoteRepository::class)]
#[ORM\Table(name: 'veloce_votes')]
#[ORM\Index(columns: ['user_id'], name: 'idx_vote_user')]
#[ORM\Index(columns: ['entity_id', 'vote_entity'], name: 'idx_vote_entity')]
#[ORM\UniqueConstraint(name: 'unique_user_vote', columns: ['user_id', 'entity_id', 'vote_entity'])]
class Vote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['vote:read'])]
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
    #[Groups(['vote:read'])]
    private ?User $user = null {
        get => $this->user;
    }

    #[ORM\Column(name: 'entity_id', type: 'integer')]
    #[Groups(['vote:read'])]
    public private(set) int $entityId {
        get => $this->entityId;
    }

    #[ORM\Column(name: 'vote_entity', type: 'integer', enumType: VoteEntity::class)]
    #[Groups(['vote:read'])]
    public private(set) VoteEntity $voteEntity {
        get => $this->voteEntity;
    }

    #[ORM\Column(name: 'vote_type', type: 'integer', enumType: VoteType::class)]
    #[Groups(['vote:read'])]
    public private(set) VoteType $voteType {
        get => $this->voteType;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function isLike(): bool
    {
        return $this->voteType === VoteType::LIKE;
    }

    public function isDislike(): bool
    {
        return $this->voteType === VoteType::DISLIKE;
    }

    public function updateType(VoteType $type): static
    {
        $this->voteType = $type;
        return $this;
    }

    public static function create(int $userId, int $entityId, VoteEntity $voteEntity, VoteType $voteType): self
    {
        $vote = new self();
        $vote->userId = $userId;
        $vote->entityId = $entityId;
        $vote->voteEntity = $voteEntity;
        $vote->voteType = $voteType;
        return $vote;
    }
}
