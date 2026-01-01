<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Guild;

use App\Domain\Guild\Repository\GuildMemberRepository;
use App\Domain\Guild\Repository\GuildRepository;
use App\Domain\User\Entity\User;
use App\Presentation\Controller\Api\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/guilds', name: 'api_guilds_')]
final class GuildController extends AbstractApiController
{
    public function __construct(
        private readonly GuildRepository $guildRepository,
        private readonly GuildMemberRepository $guildMemberRepository,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Get paginated guild list (public).
     */
    #[Route('/list/{page<\d+>}/{perPage<\d+>}', name: 'list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function list(int $page, int $perPage): JsonResponse
    {
        $result = $this->guildRepository->findPaginated($page, min($perPage, 50));

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
     * Search guilds (public).
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

        $result = $this->guildRepository->search($query, $page, min($perPage, 50));

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['guild:search']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get single guild details (public).
     */
    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $guild = $this->guildRepository->findWithRelations($id);

        if (!$guild) {
            return $this->notFound('Guild not found');
        }

        return $this->success(
            $this->normalizer->normalize($guild, 'json', ['groups' => ['guild:read']])
        );
    }

    /**
     * Get guild with most members (public).
     */
    #[Route('/most/members', name: 'most_members', methods: ['GET'])]
    public function mostMembers(): JsonResponse
    {
        $guild = $this->guildRepository->findMostMembers();

        if (!$guild) {
            return $this->success(null);
        }

        return $this->success(
            $this->normalizer->normalize($guild, 'json', ['groups' => ['guild:list']])
        );
    }

    /**
     * Get popular guilds (public).
     */
    #[Route('/popular', name: 'popular', methods: ['GET'])]
    public function popular(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', '10');
        $guilds = $this->guildRepository->findPopular(min($limit, 50));

        return $this->success(
            $this->normalizer->normalize($guilds, 'json', ['groups' => ['guild:list']])
        );
    }

    /**
     * Get guild members (public).
     */
    #[Route('/{id<\d+>}/members/{page<\d+>}/{perPage<\d+>}', name: 'members', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function members(int $id, int $page, int $perPage): JsonResponse
    {
        $guild = $this->guildRepository->find($id);

        if (!$guild) {
            return $this->notFound('Guild not found');
        }

        $result = $this->guildMemberRepository->findByGuildPaginated($id, $page, min($perPage, 50));

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['member:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get guilds a user is member of (public).
     */
    #[Route('/user/{userId<\d+>}/{page<\d+>}/{perPage<\d+>}', name: 'by_user', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function byUser(int $userId, int $page, int $perPage): JsonResponse
    {
        $result = $this->guildMemberRepository->findByUserPaginated($userId, $page, min($perPage, 50));

        // Extract guilds from memberships
        $guilds = array_map(fn($m) => $m->getGuild(), $result['items']);
        $guilds = array_filter($guilds); // Remove nulls

        return $this->success([
            'items' => $this->normalizer->normalize($guilds, 'json', ['groups' => ['guild:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get current user's guilds (authenticated).
     */
    #[Route('/my/{page<\d+>}/{perPage<\d+>}', name: 'my_guilds', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function myGuilds(
        #[CurrentUser] User $user,
        int $page,
        int $perPage,
    ): JsonResponse {
        $result = $this->guildMemberRepository->findByUserPaginated($user->getId(), $page, min($perPage, 50));

        // Extract guilds from memberships with membership info
        $items = array_map(function($m) {
            $guild = $m->getGuild();
            if (!$guild) {
                return null;
            }
            return [
                'guild' => $this->normalizer->normalize($guild, 'json', ['groups' => ['guild:list']]),
                'membership' => [
                    'rank' => $m->getRank(),
                    'level_id' => $m->levelId,
                    'joined_at' => $m->getJoinedAt()->format('c'),
                ],
            ];
        }, $result['items']);

        return $this->success([
            'items' => array_filter($items),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Check if current user is member of a guild (authenticated).
     */
    #[Route('/{id<\d+>}/membership', name: 'check_membership', methods: ['GET'])]
    public function checkMembership(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $membership = $this->guildMemberRepository->findMembership($id, $user->getId());

        if (!$membership) {
            return $this->success([
                'is_member' => false,
            ]);
        }

        return $this->success([
            'is_member' => true,
            'rank' => $membership->getRank(),
            'level_id' => $membership->levelId,
            'joined_at' => $membership->getJoinedAt()->format('c'),
            'is_admin' => $membership->isAdmin(),
            'has_rights' => $membership->hasRights(),
        ]);
    }

    /**
     * Get guild statistics (public).
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return $this->success([
            'total' => $this->guildRepository->countAll(),
        ]);
    }
}
