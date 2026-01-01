<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Community;

use App\Domain\Article\Repository\ArticleRepository;
use App\Domain\Guild\Repository\GuildRepository;
use App\Domain\Room\Repository\RoomRepository;
use App\Domain\User\Repository\UserRepository;
use App\Presentation\Controller\Api\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/community', name: 'api_community_')]
final class CommunityController extends AbstractApiController
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly RoomRepository $roomRepository,
        private readonly GuildRepository $guildRepository,
        private readonly UserRepository $userRepository,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Search rooms by name/description.
     */
    #[Route('/search/rooms/{term}/{page<\d+>}/{perPage<\d+>}', name: 'search_rooms', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function searchRooms(string $term, int $page, int $perPage): JsonResponse
    {
        $result = $this->roomRepository->search($term, $page, $perPage);

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
     * Search guilds by name.
     */
    #[Route('/search/guilds/{term}/{page<\d+>}/{perPage<\d+>}', name: 'search_guilds', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function searchGuilds(string $term, int $page, int $perPage): JsonResponse
    {
        $result = $this->guildRepository->search($term, $page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['guild:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Search articles by title/content.
     */
    #[Route('/search/articles/{term}/{page<\d+>}/{perPage<\d+>}', name: 'search_articles', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function searchArticles(string $term, int $page, int $perPage): JsonResponse
    {
        $result = $this->articleRepository->search($term, $page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['article:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Search users by username.
     */
    #[Route('/search/users/{term}/{page<\d+>}/{perPage<\d+>}', name: 'search_users', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function searchUsers(string $term, int $page, int $perPage): JsonResponse
    {
        $result = $this->userRepository->search($term, $page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['user:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Global search across all entities.
     */
    #[Route('/search/{term}', name: 'search_all', methods: ['GET'])]
    public function searchAll(string $term): JsonResponse
    {
        $limit = 5;

        return $this->success([
            'articles' => $this->normalizer->normalize(
                $this->articleRepository->search($term, 1, $limit)['items'],
                'json',
                ['groups' => ['article:list']]
            ),
            'rooms' => $this->normalizer->normalize(
                $this->roomRepository->search($term, 1, $limit)['items'],
                'json',
                ['groups' => ['room:list']]
            ),
            'guilds' => $this->normalizer->normalize(
                $this->guildRepository->search($term, 1, $limit)['items'],
                'json',
                ['groups' => ['guild:list']]
            ),
            'users' => $this->normalizer->normalize(
                $this->userRepository->search($term, 1, $limit)['items'],
                'json',
                ['groups' => ['user:list']]
            ),
        ]);
    }

    /**
     * Get community statistics.
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return $this->success([
            'users' => [
                'total' => $this->userRepository->countAll(),
                'online' => $this->userRepository->countOnline(),
            ],
            'rooms' => [
                'total' => $this->roomRepository->countAll(),
                'public' => $this->roomRepository->countPublic(),
            ],
            'guilds' => [
                'total' => $this->guildRepository->countAll(),
            ],
        ]);
    }

    /**
     * Get community highlights (popular rooms, guilds, etc.).
     */
    #[Route('/highlights', name: 'highlights', methods: ['GET'])]
    public function highlights(): JsonResponse
    {
        return $this->success([
            'popularRooms' => $this->normalizer->normalize(
                $this->roomRepository->findPopular(5),
                'json',
                ['groups' => ['room:list']]
            ),
            'popularGuilds' => $this->normalizer->normalize(
                $this->guildRepository->findPopular(5),
                'json',
                ['groups' => ['guild:list']]
            ),
            'mostVisitedRoom' => $this->normalizer->normalize(
                $this->roomRepository->findMostVisited(),
                'json',
                ['groups' => ['room:read']]
            ),
            'biggestGuild' => $this->normalizer->normalize(
                $this->guildRepository->findMostMembers(),
                'json',
                ['groups' => ['guild:read']]
            ),
        ]);
    }

    /**
     * Get users currently online.
     */
    #[Route('/online/{page<\d+>}/{perPage<\d+>}', name: 'online', defaults: ['page' => 1, 'perPage' => 50], methods: ['GET'])]
    public function online(int $page, int $perPage): JsonResponse
    {
        $result = $this->userRepository->findOnlinePaginated($page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['user:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get rooms with active users.
     */
    #[Route('/active-rooms', name: 'active_rooms', methods: ['GET'])]
    public function activeRooms(): JsonResponse
    {
        $rooms = $this->roomRepository->findWithUsers(20);

        return $this->success(
            $this->normalizer->normalize($rooms, 'json', ['groups' => ['room:list']])
        );
    }
}
