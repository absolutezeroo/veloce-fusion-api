<?php

declare(strict_types=1);

namespace App\Domain\Auth\Service;

use App\Domain\Auth\Entity\RefreshToken;
use App\Domain\Auth\Repository\RefreshTokenRepository;
use App\Domain\User\Entity\User;

final class RefreshTokenService
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly int $refreshTokenTtl = 604800, // 7 days
        private readonly int $maxSessionsPerUser = 5,
    ) {}

    /**
     * Create a new refresh token for a user.
     */
    public function createRefreshToken(
        User $user,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): RefreshToken {
        // Limit active sessions per user
        $this->enforceSessionLimit($user);

        $refreshToken = RefreshToken::create(
            user: $user,
            ttl: $this->refreshTokenTtl,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        return $this->refreshTokenRepository->save($refreshToken);
    }

    /**
     * Validate a refresh token and return the associated user.
     * Does NOT consume/rotate the token - call consumeRefreshToken for that.
     */
    public function validateRefreshToken(string $token): ?User
    {
        return $this->refreshTokenRepository->findValidToken($token)?->getUser();
    }

    /**
     * Consume a refresh token (validate + revoke old + create new).
     * Implements token rotation for security.
     *
     * @return array{user: User, newToken: RefreshToken}|null
     */
    public function consumeRefreshToken(
        string $token,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ?array {
        $refreshToken = $this->refreshTokenRepository->findValidToken($token);

        if (!$refreshToken) {
            return null;
        }

        $user = $refreshToken->getUser();

        if (!$user) {
            return null;
        }

        // Revoke old token
        $this->refreshTokenRepository->revoke($refreshToken);

        // Create new token (rotation)
        $newToken = $this->createRefreshToken($user, $ipAddress, $userAgent);

        return [
            'user' => $user,
            'newToken' => $newToken,
        ];
    }

    /**
     * Revoke a specific refresh token.
     */
    public function revokeToken(string $token): bool
    {
        $refreshToken = $this->refreshTokenRepository->findValidToken($token);

        if (!$refreshToken) {
            return false;
        }

        $this->refreshTokenRepository->revoke($refreshToken);

        return true;
    }

    /**
     * Revoke all refresh tokens for a user (logout everywhere).
     */
    public function revokeAllTokens(User $user): int
    {
        return $this->refreshTokenRepository->revokeAllForUser($user);
    }

    /**
     * Get active sessions for a user.
     *
     * @return RefreshToken[]
     */
    public function getActiveSessions(User $user): array
    {
        return $this->refreshTokenRepository->findActiveByUser($user);
    }

    /**
     * Get refresh token TTL in seconds.
     */
    public function getTtl(): int
    {
        return $this->refreshTokenTtl;
    }

    /**
     * Clean up expired tokens (call via cron/scheduler).
     */
    public function cleanupExpiredTokens(): int
    {
        return $this->refreshTokenRepository->deleteExpired();
    }

    /**
     * Enforce maximum sessions per user by removing oldest sessions.
     */
    private function enforceSessionLimit(User $user): void
    {
        $activeCount = $this->refreshTokenRepository->countActiveByUser($user);

        if ($activeCount >= $this->maxSessionsPerUser) {
            $sessions = $this->refreshTokenRepository->findActiveByUser($user);

            // Remove oldest sessions to make room
            $toRemove = array_slice($sessions, $this->maxSessionsPerUser - 1);

            foreach ($toRemove as $session) {
                $this->refreshTokenRepository->revoke($session);
            }
        }
    }
}
