<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Photo;

use App\Domain\Photo\Repository\PhotoRepository;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use App\Domain\Vote\Entity\Vote;
use App\Domain\Vote\Enum\VoteEntity;
use App\Domain\Vote\Enum\VoteType;
use App\Domain\Vote\Repository\VoteRepository;
use App\Presentation\Controller\Api\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/photos', name: 'api_photos_')]
final class PhotoController extends AbstractApiController
{
    public function __construct(
        private readonly PhotoRepository $photoRepository,
        private readonly UserRepository $userRepository,
        private readonly VoteRepository $voteRepository,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Get paginated photo list (public).
     */
    #[Route('/list/{page<\d+>}/{perPage<\d+>}', name: 'list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function list(int $page, int $perPage): JsonResponse
    {
        $result = $this->photoRepository->findPaginated($page, min($perPage, 50));

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
     * Get single photo (public).
     */
    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $photo = $this->photoRepository->findWithUser($id);

        if (!$photo) {
            return $this->notFound('Photo not found');
        }

        return $this->success(
            $this->normalizer->normalize($photo, 'json', ['groups' => ['photo:read']])
        );
    }

    /**
     * Search photos by username (public).
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $username = $request->query->get('username', '');
        $page = (int) $request->query->get('page', '1');
        $perPage = (int) $request->query->get('perPage', '20');

        if (strlen($username) < 2) {
            return $this->validationError(['username' => 'Username must be at least 2 characters']);
        }

        $result = $this->photoRepository->searchByUsername($username, $page, min($perPage, 50));

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
     * Get photos by user (public).
     */
    #[Route('/user/{userId<\d+>}/{page<\d+>}/{perPage<\d+>}', name: 'by_user', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function byUser(int $userId, int $page, int $perPage): JsonResponse
    {
        $user = $this->userRepository->find($userId);

        if (!$user) {
            return $this->notFound('User not found');
        }

        $result = $this->photoRepository->findByUserPaginated($userId, $page, min($perPage, 50));

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
     * Get photos from a room (public).
     */
    #[Route('/room/{roomId<\d+>}/{page<\d+>}/{perPage<\d+>}', name: 'by_room', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function byRoom(int $roomId, int $page, int $perPage): JsonResponse
    {
        $result = $this->photoRepository->findByRoomPaginated($roomId, $page, min($perPage, 50));

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
     * Get most liked photos (public).
     */
    #[Route('/popular', name: 'popular', methods: ['GET'])]
    public function popular(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', '10');
        $photos = $this->photoRepository->findMostLiked(min($limit, 50));

        return $this->success(
            $this->normalizer->normalize($photos, 'json', ['groups' => ['photo:list']])
        );
    }

    /**
     * Get current user's photos (authenticated).
     */
    #[Route('/my/{page<\d+>}/{perPage<\d+>}', name: 'my_photos', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function myPhotos(
        #[CurrentUser] User $user,
        int $page,
        int $perPage,
    ): JsonResponse {
        $result = $this->photoRepository->findByUserPaginated($user->getId(), $page, min($perPage, 50));

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
     * Like a photo (authenticated).
     */
    #[Route('/{id<\d+>}/like', name: 'like', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function like(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $photo = $this->photoRepository->find($id);

        if (!$photo) {
            return $this->notFound('Photo not found');
        }

        return $this->handleVote($id, $user->getId(), VoteType::LIKE);
    }

    /**
     * Dislike a photo (authenticated).
     */
    #[Route('/{id<\d+>}/dislike', name: 'dislike', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function dislike(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $photo = $this->photoRepository->find($id);

        if (!$photo) {
            return $this->notFound('Photo not found');
        }

        return $this->handleVote($id, $user->getId(), VoteType::DISLIKE);
    }

    /**
     * Remove vote from a photo (authenticated).
     */
    #[Route('/{id<\d+>}/vote', name: 'remove_vote', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function removeVote(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $photo = $this->photoRepository->find($id);

        if (!$photo) {
            return $this->notFound('Photo not found');
        }

        $existingVote = $this->voteRepository->findExistingVote($user->getId(), $id, VoteEntity::PHOTO);

        if (!$existingVote) {
            return $this->error('No vote found', 404);
        }

        $this->voteRepository->remove($existingVote);

        $counts = $this->voteRepository->getVoteCountsForEntity($id, VoteEntity::PHOTO);

        return $this->success([
            'message' => 'Vote removed',
            'likes' => $counts['likes'],
            'dislikes' => $counts['dislikes'],
            'user_vote' => null,
        ]);
    }

    /**
     * Get user's vote on a photo (authenticated).
     */
    #[Route('/{id<\d+>}/vote', name: 'get_vote', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getVote(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $photo = $this->photoRepository->find($id);

        if (!$photo) {
            return $this->notFound('Photo not found');
        }

        $existingVote = $this->voteRepository->findExistingVote($user->getId(), $id, VoteEntity::PHOTO);
        $counts = $this->voteRepository->getVoteCountsForEntity($id, VoteEntity::PHOTO);

        return $this->success([
            'likes' => $counts['likes'],
            'dislikes' => $counts['dislikes'],
            'user_vote' => $existingVote?->voteType->value,
        ]);
    }

    /**
     * Delete a photo (owner or admin).
     */
    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $photo = $this->photoRepository->find($id);

        if (!$photo) {
            return $this->notFound('Photo not found');
        }

        // Check ownership (admin check could be added with roles)
        if ($photo->userId !== $user->getId()) {
            return $this->error('You can only delete your own photos', 403);
        }

        $this->photoRepository->remove($photo);

        return $this->success(['message' => 'Photo deleted']);
    }

    /**
     * Get photo statistics (public).
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        return $this->success([
            'total' => $this->photoRepository->countAll(),
        ]);
    }

    /**
     * Handle vote logic (like/dislike with toggle).
     */
    private function handleVote(int $photoId, int $userId, VoteType $voteType): JsonResponse
    {
        $existingVote = $this->voteRepository->findExistingVote($userId, $photoId, VoteEntity::PHOTO);

        if ($existingVote) {
            if ($existingVote->voteType === $voteType) {
                // Same vote type = remove vote (toggle off)
                $this->voteRepository->remove($existingVote);
                $message = 'Vote removed';
                $userVote = null;
            } else {
                // Different vote type = change vote
                $existingVote->updateType($voteType);
                $this->voteRepository->save($existingVote);
                $message = $voteType === VoteType::LIKE ? 'Changed to like' : 'Changed to dislike';
                $userVote = $voteType->value;
            }
        } else {
            // New vote
            $vote = Vote::create($userId, $photoId, VoteEntity::PHOTO, $voteType);
            $this->voteRepository->save($vote);
            $message = $voteType === VoteType::LIKE ? 'Photo liked' : 'Photo disliked';
            $userVote = $voteType->value;
        }

        $counts = $this->voteRepository->getVoteCountsForEntity($photoId, VoteEntity::PHOTO);

        return $this->success([
            'message' => $message,
            'likes' => $counts['likes'],
            'dislikes' => $counts['dislikes'],
            'user_vote' => $userVote,
        ]);
    }
}
