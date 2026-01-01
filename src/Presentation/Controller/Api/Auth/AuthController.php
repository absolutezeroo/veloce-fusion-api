<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Auth;

use App\Application\User\DTO\RegisterUserDTO;
use App\Domain\User\Service\RegisterUserService;
use App\Presentation\Controller\Api\AbstractApiController;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/auth', name: 'api_auth_')]
final class AuthController extends AbstractApiController
{
    public function __construct(
        private readonly RegisterUserService $registerUserService,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly SerializerInterface $serializer,
    ) {}

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        #[MapRequestPayload] RegisterUserDTO $dto,
    ): JsonResponse {
        $ipAddress = $request->getClientIp() ?? '127.0.0.1';

        $user = $this->registerUserService->register($dto, $ipAddress);
        $token = $this->jwtManager->create($user);

        return $this->success([
            'token' => $token,
            'user' => $this->serializer->normalize($user, 'json', ['groups' => ['user:read']]),
        ], 'Registration successful');
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->error('Not authenticated', 401);
        }

        return $this->success(
            $this->serializer->normalize($user, 'json', ['groups' => ['user:read']])
        );
    }
}
