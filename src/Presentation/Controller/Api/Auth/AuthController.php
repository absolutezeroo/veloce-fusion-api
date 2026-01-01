<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Auth;

use App\Application\User\DTO\RegisterUserDTO;
use App\Domain\Auth\Service\RefreshTokenService;
use App\Domain\User\Entity\User;
use App\Domain\User\Service\RegisterUserService;
use App\Presentation\Controller\Api\AbstractApiController;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/auth', name: 'api_auth_')]
final class AuthController extends AbstractApiController
{
    public function __construct(
        private readonly RegisterUserService $registerUserService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly NormalizerInterface $normalizer,
        private readonly string $appEnv,
    ) {}

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        #[MapRequestPayload] RegisterUserDTO $dto,
    ): JsonResponse {
        $ipAddress = $request->getClientIp() ?? '127.0.0.1';

        $user = $this->registerUserService->register($dto, $ipAddress);
        $token = $this->jwtManager->create($user);

        $response = $this->success([
            'token' => $token,
            'user' => $this->normalizer->normalize($user, 'json', ['groups' => ['user:read']]),
        ], 'Registration successful');

        // Set refresh token cookie
        $this->setRefreshTokenCookie($response, $user, $request);

        return $response;
    }

    /**
     * Refresh access token using refresh token cookie.
     */
    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->cookies->get('refresh_token');

        if (!$refreshToken) {
            return $this->error('No refresh token provided', 401);
        }

        $result = $this->refreshTokenService->consumeRefreshToken(
            token: $refreshToken,
            ipAddress: $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
        );

        if (!$result) {
            $response = $this->error('Invalid or expired refresh token', 401);
            $this->clearRefreshTokenCookie($response);
            return $response;
        }

        $accessToken = $this->jwtManager->create($result['user']);

        $response = $this->success([
            'token' => $accessToken,
        ]);

        // Set new refresh token cookie (rotation)
        $cookie = Cookie::create('refresh_token')
            ->withValue($result['newToken']->token)
            ->withExpires(time() + $this->refreshTokenService->getTtl())
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSecure($this->appEnv !== 'dev')
            ->withSameSite(Cookie::SAMESITE_LAX);

        $response->headers->setCookie($cookie);

        return $response;
    }

    /**
     * Logout and revoke refresh token.
     */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $refreshToken = $request->cookies->get('refresh_token');

        if ($refreshToken) {
            $this->refreshTokenService->revokeToken($refreshToken);
        }

        $response = $this->success(['message' => 'Logged out successfully']);
        $this->clearRefreshTokenCookie($response);

        return $response;
    }

    /**
     * Logout from all devices (revoke all refresh tokens).
     */
    #[Route('/logout-all', name: 'logout_all', methods: ['POST'])]
    public function logoutAll(#[CurrentUser] User $user): JsonResponse
    {
        $revokedCount = $this->refreshTokenService->revokeAllTokens($user);

        $response = $this->success([
            'message' => 'Logged out from all devices',
            'revoked_sessions' => $revokedCount,
        ]);

        $this->clearRefreshTokenCookie($response);

        return $response;
    }

    /**
     * Get active sessions for current user.
     */
    #[Route('/sessions', name: 'sessions', methods: ['GET'])]
    public function sessions(#[CurrentUser] User $user): JsonResponse
    {
        $sessions = $this->refreshTokenService->getActiveSessions($user);

        return $this->success([
            'sessions' => array_map(fn($s) => [
                'id' => $s->getId(),
                'ip_address' => $s->ipAddress,
                'user_agent' => $s->userAgent,
                'created_at' => $s->createdAt->format('c'),
                'expires_at' => $s->expiresAt->format('c'),
            ], $sessions),
        ]);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->error('Not authenticated', 401);
        }

        return $this->success(
            $this->normalizer->normalize($user, 'json', ['groups' => ['user:read']])
        );
    }

    private function setRefreshTokenCookie(JsonResponse $response, User $user, Request $request): void
    {
        $refreshToken = $this->refreshTokenService->createRefreshToken(
            user: $user,
            ipAddress: $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
        );

        $cookie = Cookie::create('refresh_token')
            ->withValue($refreshToken->token)
            ->withExpires(time() + $this->refreshTokenService->getTtl())
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSecure($this->appEnv !== 'dev')
            ->withSameSite(Cookie::SAMESITE_LAX);

        $response->headers->setCookie($cookie);
    }

    private function clearRefreshTokenCookie(JsonResponse $response): void
    {
        $cookie = Cookie::create('refresh_token')
            ->withValue('')
            ->withExpires(time() - 3600)
            ->withPath('/')
            ->withHttpOnly(true);

        $response->headers->setCookie($cookie);
    }
}
