<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Room;

use App\Domain\Room\Repository\RoomRepository;
use App\Domain\User\Entity\User;
use App\Presentation\Controller\Api\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/rooms', name: 'api_rooms_')]
final class RoomController extends AbstractApiController
{
    public function __construct(
        private readonly RoomRepository $roomRepository,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Get paginated room list (public).
     */
    #[Route('/list/{page<\d+>}/{perPage<\d+>}', name: 'list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function list(int $page, int $perPage, Request $request): JsonResponse
    {
        $categoryId = $request->query->get('category') ? (int) $request->query->get('category') : null;

        $result = $this->roomRepository->findPaginated($page, min($perPage, 50), $categoryId);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['room:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Search rooms (public).
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $page = (int) $request->query->get('page', '1');
        $perPage = (int) $request->query->get('perPage', '20');

        if (strlen($query) < 2) {
            return $this->validationError(['q' => 'Query must be at least 2 characters']);
        }

        $result = $this->roomRepository->search($query, $page, min($perPage, 50));

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['room:search']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get single room details (public).
     */
    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $room = $this->roomRepository->findWithOwner($id);

        if (!$room) {
            return $this->notFound('Room not found');
        }

        return $this->success(
            $this->normalizer->normalize($room, 'json', ['groups' => ['room:read']])
        );
    }

    /**
     * Get most visited room (public).
     */
    #[Route('/most-visited', name: 'most_visited', methods: ['GET'])]
    public function mostVisited(): JsonResponse
    {
        $room = $this->roomRepository->findMostVisited();

        if (!$room) {
            return $this->success(null);
        }

        return $this->success(
            $this->normalizer->normalize($room, 'json', ['groups' => ['room:list']])
        );
    }

    /**
     * Get popular rooms (public).
     */
    #[Route('/popular', name: 'popular', methods: ['GET'])]
    public function popular(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', '10');
        $rooms = $this->roomRepository->findPopular(min($limit, 50));

        return $this->success(
            $this->normalizer->normalize($rooms, 'json', ['groups' => ['room:list']])
        );
    }

    /**
     * Get rooms with online users (public).
     */
    #[Route('/active', name: 'active', methods: ['GET'])]
    public function active(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', '20');
        $rooms = $this->roomRepository->findWithUsers(min($limit, 50));

        return $this->success(
            $this->normalizer->normalize($rooms, 'json', ['groups' => ['room:list']])
        );
    }

    /**
     * Get rooms owned by a user (public).
     */
    #[Route('/user/{userId<\d+>}/{page<\d+>}/{perPage<\d+>}', name: 'by_user', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function byUser(int $userId, int $page, int $perPage): JsonResponse
    {
        $result = $this->roomRepository->findByOwner($userId, $page, min($perPage, 50));

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['room:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get current user's rooms (authenticated).
     */
    #[Route('/my/{page<\d+>}/{perPage<\d+>}', name: 'my_rooms', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function myRooms(
        #[CurrentUser] User $user,
        int $page,
        int $perPage,
    ): JsonResponse {
        $result = $this->roomRepository->findByOwner($user->getId(), $page, min($perPage, 50));

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['room:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get room statistics (public).
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return $this->success([
            'total' => $this->roomRepository->countAll(),
            'public' => $this->roomRepository->countPublic(),
        ]);
    }
}
