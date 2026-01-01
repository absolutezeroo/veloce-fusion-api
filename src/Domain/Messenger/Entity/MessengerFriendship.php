<?php

declare(strict_types=1);

namespace App\Domain\Messenger\Entity;

use App\Domain\Messenger\Repository\MessengerFriendshipRepository;
use App\Domain\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: MessengerFriendshipRepository::class)]
#[ORM\Table(name: 'messenger_friendships')]
#[ORM\Index(columns: ['user_one_id'], name: 'idx_friendship_user_one')]
#[ORM\Index(columns: ['user_two_id'], name: 'idx_friendship_user_two')]
class MessengerFriendship
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['friendship:read', 'friendship:list'])]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(name: 'user_one_id', type: 'integer')]
    #[Ignore]
    public private(set) int $userOneId {
        get => $this->userOneId;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_one_id', referencedColumnName: 'id', nullable: false)]
    #[Ignore]
    private ?User $userOne = null {
        get => $this->userOne;
    }

    #[ORM\Column(name: 'user_two_id', type: 'integer')]
    #[Ignore]
    public private(set) int $userTwoId {
        get => $this->userTwoId;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_two_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['friendship:list', 'friend:list'])]
    private ?User $userTwo = null {
        get => $this->userTwo;
    }

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['friendship:read', 'friendship:list'])]
    public private(set) int $relation = 0 {
        get => $this->relation;
    }

    #[ORM\Column(name: 'friends_since', type: 'integer')]
    #[Groups(['friendship:read', 'friendship:list'])]
    public private(set) int $friendsSince {
        get => $this->friendsSince;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserOne(): ?User
    {
        return $this->userOne;
    }

    public function getUserTwo(): ?User
    {
        return $this->userTwo;
    }

    /**
     * Get the friend user (userTwo) for display purposes.
     */
    #[Groups(['friend:list'])]
    public function getFriend(): ?User
    {
        return $this->userTwo;
    }

    /**
     * Get friends since as DateTime.
     */
    #[Groups(['friendship:read', 'friend:list'])]
    public function getFriendsSinceDate(): \DateTimeImmutable
    {
        return (new \DateTimeImmutable())->setTimestamp($this->friendsSince);
    }

    public static function create(int $userOneId, int $userTwoId, int $relation = 0): self
    {
        $friendship = new self();
        $friendship->userOneId = $userOneId;
        $friendship->userTwoId = $userTwoId;
        $friendship->relation = $relation;
        $friendship->friendsSince = time();

        return $friendship;
    }
}
