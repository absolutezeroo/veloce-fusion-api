<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Profile;

use App\Domain\Guild\Repository\GuildMemberRepository;
use App\Domain\Messenger\Repository\MessengerFriendshipRepository;
use App\Domain\Photo\Repository\PhotoRepository;
use App\Domain\Room\Repository\RoomRepository;
use App\Domain\User\Repository\UserBadgeRepository;
use App\Domain\User\Repository\UserRepository;
use App\Presentation\Controller\Api\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/profiles', name: 'api_profiles_')]
final class ProfileController extends AbstractApiController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RoomRepository $roomRepository,
        private readonly GuildMemberRepository $guildMemberRepository,
        private readonly MessengerFriendshipRepository $messengerRepository,
        private readonly PhotoRepository $photoRepository,
        private readonly UserBadgeRepository $userBadgeRepository,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Get user profile by username.
     */
    #[Route('/user/{username}', name: 'by_username', methods: ['GET'])]
    public function getByUsername(string $username): JsonResponse
    {
        $user = $this->userRepository->findByUsername($username);

        if (!$user) {
            return $this->notFound('User not found');
        }

        return $this->success(
            $this->normalizer->normalize($user, 'json', ['groups' => ['user:profile']])
        );
    }

    /**
     * Get user profile by ID.
     */
    #[Route('/{id<\d+>}', name: 'by_id', methods: ['GET'])]
    public function getById(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->notFound('User not found');
        }

        return $this->success(
            $this->normalizer->normalize($user, 'json', ['groups' => ['user:profile']])
        );
    }

    /**
     * Get slotted badges for a profile (visible on profile).
     */
    #[Route('/{profileId<\d+>}/badges/slot', name: 'badges_slot', methods: ['GET'])]
    public function slotBadges(int $profileId): JsonResponse
    {
        $badges = $this->userBadgeRepository->findSlotted($profileId);

        return $this->success(
            $this->normalizer->normalize($badges, 'json', ['groups' => ['badge:slot']])
        );
    }

    /**
     * Get paginated badge list for a profile.
     */
    #[Route('/{profileId<\d+>}/badges/{page<\d+>}/{perPage<\d+>}', name: 'badges_list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function badgeList(int $profileId, int $page, int $perPage): JsonResponse
    {
        $result = $this->userBadgeRepository->findByUserPaginated($profileId, $page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['badge:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get paginated friend list for a profile.
     */
    #[Route('/{profileId<\d+>}/friends/{page<\d+>}/{perPage<\d+>}', name: 'friends_list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function friendList(int $profileId, int $page, int $perPage): JsonResponse
    {
        $result = $this->messengerRepository->findFriendsPaginated($profileId, $page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['friend:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get paginated room list for a profile.
     */
    #[Route('/{profileId<\d+>}/rooms/{page<\d+>}/{perPage<\d+>}', name: 'rooms_list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function roomList(int $profileId, int $page, int $perPage): JsonResponse
    {
        $result = $this->roomRepository->findByOwner($profileId, $page, $perPage);

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
     * Get paginated guild list for a profile.
     */
    #[Route('/{profileId<\d+>}/guilds/{page<\d+>}/{perPage<\d+>}', name: 'guilds_list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function guildList(int $profileId, int $page, int $perPage): JsonResponse
    {
        $result = $this->guildMemberRepository->findByUserPaginated($profileId, $page, $perPage);

        // Extract guilds from memberships
        $guilds = array_map(
            fn($membership) => $membership->getGuild(),
            $result['items']
        );

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
     * Get paginated photo list for a profile.
     */
    #[Route('/{profileId<\d+>}/photos/{page<\d+>}/{perPage<\d+>}', name: 'photos_list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function photoList(int $profileId, int $page, int $perPage): JsonResponse
    {
        $result = $this->photoRepository->findByUserPaginated($profileId, $page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['photo:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get profile statistics (counts).
     */
    #[Route('/{profileId<\d+>}/stats', name: 'stats', methods: ['GET'])]
    public function stats(int $profileId): JsonResponse
    {
        return $this->success([
            'badges' => $this->userBadgeRepository->countByUser($profileId),
            'friends' => $this->messengerRepository->countFriends($profileId),
            'rooms' => $this->roomRepository->countByOwner($profileId),
            'photos' => $this->photoRepository->countByUser($profileId),
        ]);
    }
}
