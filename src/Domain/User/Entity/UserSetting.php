<?php

declare(strict_types=1);

namespace App\Domain\User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity]
#[ORM\Table(name: 'users_settings')]
class UserSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id {
        get => $this->id;
    }

    #[ORM\OneToOne(inversedBy: 'settings')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Ignore]
    private User $user {
        get => $this->user;
    }

    #[ORM\Column(name: 'achievement_score', type: 'integer', options: ['default' => 0])]
    #[Groups(['user:read', 'settings:read'])]
    public private(set) int $achievementScore = 0 {
        get => $this->achievementScore;
    }

    #[ORM\Column(name: 'can_change_name', type: 'string', length: 1, options: ['default' => '0'])]
    #[Groups(['settings:read'])]
    public private(set) string $canChangeName = '0' {
        get => $this->canChangeName;
    }

    #[ORM\Column(name: 'block_following', type: 'string', length: 1, options: ['default' => '0'])]
    #[Groups(['settings:read'])]
    public private(set) string $blockFollowing = '0' {
        get => $this->blockFollowing;
    }

    #[ORM\Column(name: 'block_friendrequests', type: 'string', length: 1, options: ['default' => '0'])]
    #[Groups(['settings:read'])]
    public private(set) string $blockFriendRequests = '0' {
        get => $this->blockFriendRequests;
    }

    #[ORM\Column(name: 'block_roominvites', type: 'string', length: 1, options: ['default' => '0'])]
    #[Groups(['settings:read'])]
    public private(set) string $blockRoomInvites = '0' {
        get => $this->blockRoomInvites;
    }

    #[ORM\Column(name: 'block_camera_follow', type: 'string', length: 1, options: ['default' => '0'])]
    #[Groups(['settings:read'])]
    public private(set) string $blockCameraFollow = '0' {
        get => $this->blockCameraFollow;
    }

    #[ORM\Column(name: 'online_time', type: 'integer', options: ['default' => 0])]
    #[Groups(['settings:read'])]
    public private(set) int $onlineTime = 0 {
        get => $this->onlineTime;
    }

    #[ORM\Column(name: 'block_alerts', type: 'string', length: 1, options: ['default' => '0'])]
    #[Groups(['settings:read'])]
    public private(set) string $blockAlerts = '0' {
        get => $this->blockAlerts;
    }

    #[ORM\Column(name: 'ignore_bots', type: 'string', length: 1, options: ['default' => '0'])]
    #[Groups(['settings:read'])]
    public private(set) string $ignoreBots = '0' {
        get => $this->ignoreBots;
    }

    #[ORM\Column(name: 'ignore_pets', type: 'string', length: 1, options: ['default' => '0'])]
    #[Groups(['settings:read'])]
    public private(set) string $ignorePets = '0' {
        get => $this->ignorePets;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setAchievementScore(int $score): static
    {
        $this->achievementScore = $score;
        return $this;
    }

    public function setCanChangeName(string $value): static
    {
        $this->canChangeName = $value;
        return $this;
    }

    public function setBlockFollowing(string $value): static
    {
        $this->blockFollowing = $value;
        return $this;
    }

    public function setBlockFriendRequests(string $value): static
    {
        $this->blockFriendRequests = $value;
        return $this;
    }

    public function setBlockRoomInvites(string $value): static
    {
        $this->blockRoomInvites = $value;
        return $this;
    }

    public function setBlockCameraFollow(string $value): static
    {
        $this->blockCameraFollow = $value;
        return $this;
    }

    public function setOnlineTime(int $value): static
    {
        $this->onlineTime = $value;
        return $this;
    }

    public function setBlockAlerts(string $value): static
    {
        $this->blockAlerts = $value;
        return $this;
    }

    public function setIgnoreBots(string $value): static
    {
        $this->ignoreBots = $value;
        return $this;
    }

    public function setIgnorePets(string $value): static
    {
        $this->ignorePets = $value;
        return $this;
    }
}
