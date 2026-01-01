<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\User;

use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use App\Presentation\Controller\Api\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/user', name: 'api_user_')]
final class UserController extends AbstractApiController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly SerializerInterface $serializer,
    ) {}

    #[Route('', name: 'current', methods: ['GET'])]
    public function current(#[CurrentUser] User $user): JsonResponse
    {
        return $this->success(
            $this->serializer->normalize($user, 'json', ['groups' => ['user:read']])
        );
    }

    #[Route('/look', name: 'look', methods: ['POST'])]
    public function getLook(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $username = $data['username'] ?? null;

        if (!$username) {
            return $this->validationError(['username' => 'Username is required']);
        }

        $user = $this->userRepository->findByUsername($username);

        if (!$user) {
            return $this->notFound('User not found');
        }

        return $this->success([
            'username' => $user->username,
            'look' => $user->look,
            'gender' => $user->gender,
        ]);
    }

    #[Route('/online', name: 'online', methods: ['GET'])]
    public function onlineCount(): JsonResponse
    {
        $count = $this->userRepository->count(['online' => 1]);

        return $this->success(['count' => $count]);
    }

    #[Route('/ticket', name: 'ticket', methods: ['PUT'])]
    public function generateTicket(#[CurrentUser] User $user): JsonResponse
    {
        $ticket = bin2hex(random_bytes(32));

        $user->setAuthTicket($ticket);
        $this->userRepository->save($user);

        return $this->success(['ticket' => $ticket]);
    }
}
