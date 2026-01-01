<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListener;

use App\Domain\Auth\Service\RefreshTokenService;
use App\Domain\User\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds refresh token as HTTP-only cookie on successful JWT authentication.
 */
final class AuthenticationSuccessListener
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokenService,
        private readonly RequestStack $requestStack,
        private readonly string $appEnv,
    ) {}

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $ipAddress = $request?->getClientIp();
        $userAgent = $request?->headers->get('User-Agent');

        $refreshToken = $this->refreshTokenService->createRefreshToken(
            user: $user,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        $response = $event->getResponse();

        // Set refresh token as HTTP-only cookie
        $cookie = Cookie::create('refresh_token')
            ->withValue($refreshToken->token)
            ->withExpires(time() + $this->refreshTokenService->getTtl())
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSecure($this->appEnv !== 'dev')
            ->withSameSite(Cookie::SAMESITE_LAX);

        $response->headers->setCookie($cookie);
    }
}
