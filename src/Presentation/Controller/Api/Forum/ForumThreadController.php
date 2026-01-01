<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Forum;

use App\Application\Forum\DTO\CreateThreadDTO;
use App\Application\Forum\DTO\UpdateThreadDTO;
use App\Domain\Forum\Entity\ForumThread;
use App\Domain\Forum\Repository\ForumCategoryRepository;
use App\Domain\Forum\Repository\ForumThreadRepository;
use App\Domain\User\Entity\User;
use App\Domain\Vote\Entity\Vote;
use App\Domain\Vote\Enum\VoteEntity;
use App\Domain\Vote\Enum\VoteType;
use App\Domain\Vote\Repository\VoteRepository;
use App\Presentation\Controller\Api\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/forum/threads', name: 'api_forum_threads_')]
final class ForumThreadController extends AbstractApiController
{
    public function __construct(
        private readonly ForumThreadRepository $threadRepository,
        private readonly ForumCategoryRepository $categoryRepository,
        private readonly VoteRepository $voteRepository,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Get recent threads (all categories).
     */
    #[Route('/recent/{page<\d+>}/{perPage<\d+>}', name: 'recent', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function recent(int $page, int $perPage): JsonResponse
    {
        $result = $this->threadRepository->findRecentPaginated($page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['forum_thread:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get threads by category.
     */
    #[Route('/category/{categoryId<\d+>}/{page<\d+>}/{perPage<\d+>}', name: 'by_category', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function byCategory(int $categoryId, int $page, int $perPage): JsonResponse
    {
        $category = $this->categoryRepository->find($categoryId);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        $result = $this->threadRepository->findByCategoryPaginated($categoryId, $page, $perPage);

        return $this->success([
            'category' => $this->normalizer->normalize($category, 'json', ['groups' => ['forum_category:list']]),
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['forum_thread:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get single thread by ID.
     */
    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $thread = $this->threadRepository->findWithDetails($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        // Increment view count
        $thread->incrementViewCount();
        $this->threadRepository->save($thread);

        return $this->success(
            $this->normalizer->normalize($thread, 'json', ['groups' => ['forum_thread:read']])
        );
    }

    /**
     * Get thread by slug.
     */
    #[Route('/slug/{slug}', name: 'by_slug', methods: ['GET'])]
    public function bySlug(string $slug): JsonResponse
    {
        $thread = $this->threadRepository->findBySlug($slug);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        // Increment view count
        $thread->incrementViewCount();
        $this->threadRepository->save($thread);

        return $this->success(
            $this->normalizer->normalize($thread, 'json', ['groups' => ['forum_thread:read']])
        );
    }

    /**
     * Get hot/trending threads.
     */
    #[Route('/hot', name: 'hot', methods: ['GET'])]
    public function hot(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', '10');
        $threads = $this->threadRepository->findHot(min($limit, 50));

        return $this->success(
            $this->normalizer->normalize($threads, 'json', ['groups' => ['forum_thread:list']])
        );
    }

    /**
     * Search threads.
     */
    #[Route('/search/{page<\d+>}/{perPage<\d+>}', name: 'search', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function search(Request $request, int $page, int $perPage): JsonResponse
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 3) {
            return $this->validationError(['q' => 'Search query must be at least 3 characters']);
        }

        $result = $this->threadRepository->search($query, $page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['forum_thread:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get threads by user.
     */
    #[Route('/user/{userId<\d+>}/{page<\d+>}/{perPage<\d+>}', name: 'by_user', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function byUser(int $userId, int $page, int $perPage): JsonResponse
    {
        $result = $this->threadRepository->findByUserPaginated($userId, $page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['forum_thread:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Create a new thread (authenticated).
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function create(
        #[MapRequestPayload] CreateThreadDTO $dto,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $category = $this->categoryRepository->find($dto->categoryId);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        if ($category->isLocked) {
            return $this->error('This category is locked', 403);
        }

        $thread = ForumThread::create(
            categoryId: $dto->categoryId,
            userId: $user->getId(),
            title: $dto->title,
            content: $dto->content,
        );

        $this->threadRepository->save($thread);

        // Update category stats
        $category->incrementThreadCount();
        $category->updateLastActivity($thread->getId(), $thread->lastPostAt);
        $this->categoryRepository->save($category);

        return $this->created([
            'id' => $thread->getId(),
            'slug' => $thread->slug,
            'message' => 'Thread created successfully',
        ]);
    }

    /**
     * Update own thread (authenticated).
     */
    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateThreadDTO $dto,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $thread = $this->threadRepository->find($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        // Only owner or admin can edit
        if ($thread->userId !== $user->getId() && $user->rank < 5) {
            return $this->error('You can only edit your own threads', 403);
        }

        $thread->updateTitle($dto->title);
        $thread->updateContent($dto->content);
        $this->threadRepository->save($thread);

        return $this->success(
            $this->normalizer->normalize($thread, 'json', ['groups' => ['forum_thread:read']])
        );
    }

    /**
     * Delete own thread (authenticated).
     */
    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $thread = $this->threadRepository->find($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        // Only owner or admin can delete
        if ($thread->userId !== $user->getId() && $user->rank < 5) {
            return $this->error('You can only delete your own threads', 403);
        }

        // Update category stats
        $category = $this->categoryRepository->find($thread->categoryId);
        if ($category) {
            $category->decrementThreadCount();
            $this->categoryRepository->save($category, false);
        }

        $this->threadRepository->remove($thread);

        return $this->success(['deleted' => true]);
    }

    // ==================== MODERATION ENDPOINTS ====================

    /**
     * Pin a thread (mod+).
     */
    #[Route('/{id<\d+>}/pin', name: 'pin', methods: ['POST'])]
    #[IsGranted('PERMISSION_MODERATE_FORUM')]
    public function pin(int $id): JsonResponse
    {
        $thread = $this->threadRepository->find($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        $thread->pin();
        $this->threadRepository->save($thread);

        return $this->success(['pinned' => true]);
    }

    /**
     * Unpin a thread (mod+).
     */
    #[Route('/{id<\d+>}/unpin', name: 'unpin', methods: ['POST'])]
    #[IsGranted('PERMISSION_MODERATE_FORUM')]
    public function unpin(int $id): JsonResponse
    {
        $thread = $this->threadRepository->find($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        $thread->unpin();
        $this->threadRepository->save($thread);

        return $this->success(['pinned' => false]);
    }

    /**
     * Close a thread (mod+).
     */
    #[Route('/{id<\d+>}/close', name: 'close', methods: ['POST'])]
    #[IsGranted('PERMISSION_MODERATE_FORUM')]
    public function close(int $id): JsonResponse
    {
        $thread = $this->threadRepository->find($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        $thread->close();
        $this->threadRepository->save($thread);

        return $this->success(['status' => 'closed']);
    }

    /**
     * Reopen a thread (mod+).
     */
    #[Route('/{id<\d+>}/reopen', name: 'reopen', methods: ['POST'])]
    #[IsGranted('PERMISSION_MODERATE_FORUM')]
    public function reopen(int $id): JsonResponse
    {
        $thread = $this->threadRepository->find($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        $thread->open();
        $this->threadRepository->save($thread);

        return $this->success(['status' => 'open']);
    }

    /**
     * Lock a thread (mod+).
     */
    #[Route('/{id<\d+>}/lock', name: 'lock', methods: ['POST'])]
    #[IsGranted('PERMISSION_MODERATE_FORUM')]
    public function lock(int $id): JsonResponse
    {
        $thread = $this->threadRepository->find($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        $thread->lock();
        $this->threadRepository->save($thread);

        return $this->success(['status' => 'locked']);
    }

    /**
     * Move thread to another category (mod+).
     */
    #[Route('/{id<\d+>}/move', name: 'move', methods: ['POST'])]
    #[IsGranted('PERMISSION_MODERATE_FORUM')]
    public function move(int $id, Request $request): JsonResponse
    {
        $thread = $this->threadRepository->find($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        $data = $request->toArray();
        $newCategoryId = $data['categoryId'] ?? null;

        if (!$newCategoryId) {
            return $this->validationError(['categoryId' => 'Category ID is required']);
        }

        $newCategory = $this->categoryRepository->find($newCategoryId);

        if (!$newCategory) {
            return $this->notFound('Target category not found');
        }

        // Update old category stats
        $oldCategory = $this->categoryRepository->find($thread->categoryId);
        if ($oldCategory) {
            $oldCategory->decrementThreadCount();
            $this->categoryRepository->save($oldCategory, false);
        }

        // Move thread
        $thread->moveToCategory($newCategory);
        $this->threadRepository->save($thread, false);

        // Update new category stats
        $newCategory->incrementThreadCount();
        $this->categoryRepository->save($newCategory);

        return $this->success(['moved' => true, 'categoryId' => $newCategoryId]);
    }

    /**
     * Mark thread as hot (mod+).
     */
    #[Route('/{id<\d+>}/hot', name: 'mark_hot', methods: ['POST'])]
    #[IsGranted('PERMISSION_MODERATE_FORUM')]
    public function markHot(int $id): JsonResponse
    {
        $thread = $this->threadRepository->find($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        $thread->markAsHot();
        $this->threadRepository->save($thread);

        return $this->success(['isHot' => true]);
    }

    /**
     * Unmark thread as hot (mod+).
     */
    #[Route('/{id<\d+>}/unhot', name: 'unmark_hot', methods: ['POST'])]
    #[IsGranted('PERMISSION_MODERATE_FORUM')]
    public function unmarkHot(int $id): JsonResponse
    {
        $thread = $this->threadRepository->find($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        $thread->unmarkAsHot();
        $this->threadRepository->save($thread);

        return $this->success(['isHot' => false]);
    }

    // ==================== VOTE ENDPOINTS ====================

    /**
     * Like a thread (authenticated).
     */
    #[Route('/{id<\d+>}/like', name: 'like', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function like(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $thread = $this->threadRepository->find($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        return $this->handleVote($id, $user->getId(), VoteType::LIKE);
    }

    /**
     * Dislike a thread (authenticated).
     */
    #[Route('/{id<\d+>}/dislike', name: 'dislike', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function dislike(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $thread = $this->threadRepository->find($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        return $this->handleVote($id, $user->getId(), VoteType::DISLIKE);
    }

    /**
     * Get user's vote on a thread (authenticated).
     */
    #[Route('/{id<\d+>}/vote', name: 'get_vote', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getVote(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $thread = $this->threadRepository->find($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        $existingVote = $this->voteRepository->findExistingVote($user->getId(), $id, VoteEntity::FORUM);
        $counts = $this->voteRepository->getVoteCountsForEntity($id, VoteEntity::FORUM);

        return $this->success([
            'likes' => $counts['likes'],
            'dislikes' => $counts['dislikes'],
            'user_vote' => $existingVote?->voteType->value,
        ]);
    }

    /**
     * Remove vote from a thread (authenticated).
     */
    #[Route('/{id<\d+>}/vote', name: 'remove_vote', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function removeVote(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $thread = $this->threadRepository->find($id);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        $existingVote = $this->voteRepository->findExistingVote($user->getId(), $id, VoteEntity::FORUM);

        if (!$existingVote) {
            return $this->error('No vote found', 404);
        }

        $this->voteRepository->remove($existingVote);
        $counts = $this->voteRepository->getVoteCountsForEntity($id, VoteEntity::FORUM);

        return $this->success([
            'message' => 'Vote removed',
            'likes' => $counts['likes'],
            'dislikes' => $counts['dislikes'],
            'user_vote' => null,
        ]);
    }

    /**
     * Handle vote logic.
     */
    private function handleVote(int $threadId, int $userId, VoteType $voteType): JsonResponse
    {
        $existingVote = $this->voteRepository->findExistingVote($userId, $threadId, VoteEntity::FORUM);

        if ($existingVote) {
            if ($existingVote->voteType === $voteType) {
                $this->voteRepository->remove($existingVote);
                $message = 'Vote removed';
                $userVote = null;
            } else {
                $existingVote->updateType($voteType);
                $this->voteRepository->save($existingVote);
                $message = $voteType === VoteType::LIKE ? 'Changed to like' : 'Changed to dislike';
                $userVote = $voteType->value;
            }
        } else {
            $vote = Vote::create($userId, $threadId, VoteEntity::FORUM, $voteType);
            $this->voteRepository->save($vote);
            $message = $voteType === VoteType::LIKE ? 'Thread liked' : 'Thread disliked';
            $userVote = $voteType->value;
        }

        $counts = $this->voteRepository->getVoteCountsForEntity($threadId, VoteEntity::FORUM);

        return $this->success([
            'message' => $message,
            'likes' => $counts['likes'],
            'dislikes' => $counts['dislikes'],
            'user_vote' => $userVote,
        ]);
    }
}
