<?php

declare(strict_types=1);

namespace App\Domain\Auth\Entity;

use App\Domain\Auth\Repository\RefreshTokenRepository;
use App\Domain\User\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Random\RandomException;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'veloce_refresh_tokens')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_refresh_token_expires')]
#[ORM\Index(columns: ['user_id'], name: 'idx_refresh_token_user')]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id {
        get => $this->id;
    }

    #[ORM\Column(length: 128, unique: true)]
    public private(set) string $token {
        get => $this->token;
    }

    #[ORM\Column(name: 'user_id', type: 'integer')]
    public private(set) int $userId {
        get => $this->userId;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null {
        get => $this->user;
    }

    #[ORM\Column(type: 'datetime_immutable')]
    public private(set) DateTimeImmutable $expiresAt {
        get => $this->expiresAt;
    }

    #[ORM\Column(type: 'datetime_immutable')]
    public private(set) DateTimeImmutable $createdAt {
        get => $this->createdAt;
    }

    #[ORM\Column(length: 45, nullable: true)]
    public private(set) ?string $ipAddress = null {
        get => $this->ipAddress;
    }

    #[ORM\Column(length: 255, nullable: true)]
    public private(set) ?string $userAgent = null {
        get => $this->userAgent;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    /**
     * @throws RandomException
     */
    public static function create(
        User $user,
        int $ttl = 604800,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): self {
        $refreshToken = new self();
        $refreshToken->token = bin2hex(random_bytes(64));
        $refreshToken->userId = $user->getId();
        $refreshToken->user = $user;
        $refreshToken->expiresAt = new DateTimeImmutable("+{$ttl} seconds");
        $refreshToken->createdAt = new DateTimeImmutable();
        $refreshToken->ipAddress = $ipAddress;
        $refreshToken->userAgent = $userAgent ? mb_substr($userAgent, 0, 255) : null;

        return $refreshToken;
    }
}
