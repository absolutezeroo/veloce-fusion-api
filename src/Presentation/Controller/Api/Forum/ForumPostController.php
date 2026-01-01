<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Forum;

use App\Application\Forum\DTO\CreatePostDTO;
use App\Application\Forum\DTO\UpdatePostDTO;
use App\Domain\Forum\Entity\ForumPost;
use App\Domain\Forum\Repository\ForumCategoryRepository;
use App\Domain\Forum\Repository\ForumPostRepository;
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

#[Route('/forum/posts', name: 'api_forum_posts_')]
final class ForumPostController extends AbstractApiController
{
    public function __construct(
        private readonly ForumPostRepository $postRepository,
        private readonly ForumThreadRepository $threadRepository,
        private readonly ForumCategoryRepository $categoryRepository,
        private readonly VoteRepository $voteRepository,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Get posts for a thread.
     */
    #[Route('/thread/{threadId<\d+>}/{page<\d+>}/{perPage<\d+>}', name: 'by_thread', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function byThread(int $threadId, int $page, int $perPage): JsonResponse
    {
        $thread = $this->threadRepository->find($threadId);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        $result = $this->postRepository->findByThreadPaginated($threadId, $page, $perPage);

        return $this->success([
            'thread' => $this->normalizer->normalize($thread, 'json', ['groups' => ['forum_thread:list']]),
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['forum_post:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get single post by ID.
     */
    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $post = $this->postRepository->findWithDetails($id);

        if (!$post) {
            return $this->notFound('Post not found');
        }

        return $this->success(
            $this->normalizer->normalize($post, 'json', ['groups' => ['forum_post:read']])
        );
    }

    /**
     * Get posts by user.
     */
    #[Route('/user/{userId<\d+>}/{page<\d+>}/{perPage<\d+>}', name: 'by_user', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function byUser(int $userId, int $page, int $perPage): JsonResponse
    {
        $result = $this->postRepository->findByUserPaginated($userId, $page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['forum_post:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Search posts.
     */
    #[Route('/search/{page<\d+>}/{perPage<\d+>}', name: 'search', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function search(Request $request, int $page, int $perPage): JsonResponse
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 3) {
            return $this->validationError(['q' => 'Search query must be at least 3 characters']);
        }

        $result = $this->postRepository->search($query, $page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['forum_post:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Create a post/reply (authenticated).
     */
    #[Route('/thread/{threadId<\d+>}', name: 'create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function create(
        int $threadId,
        #[MapRequestPayload] CreatePostDTO $dto,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $thread = $this->threadRepository->find($threadId);

        if (!$thread) {
            return $this->notFound('Thread not found');
        }

        if (!$thread->canReply()) {
            return $this->error('This thread is closed', 403);
        }

        // Check quoted post exists if provided
        if ($dto->quotedPostId !== null) {
            $quotedPost = $this->postRepository->find($dto->quotedPostId);
            if (!$quotedPost || $quotedPost->threadId !== $threadId) {
                return $this->notFound('Quoted post not found');
            }
        }

        // Auto-approve for staff (rank >= 3)
        $autoApprove = $user->rank >= 3;

        $post = ForumPost::create(
            threadId: $threadId,
            userId: $user->getId(),
            content: $dto->content,
            quotedPostId: $dto->quotedPostId,
            autoApprove: $autoApprove,
        );

        $this->postRepository->save($post);

        // Update thread stats
        if ($autoApprove) {
            $thread->incrementReplyCount();
            $thread->updateLastPost($post->getId(), $user->getId(), new \DateTimeImmutable());
            $this->threadRepository->save($thread, false);

            // Update category stats
            $category = $this->categoryRepository->find($thread->categoryId);
            if ($category) {
                $category->incrementPostCount();
                $category->updateLastActivity($thread->getId(), new \DateTimeImmutable());
                $this->categoryRepository->save($category);
            }
        }

        return $this->created([
            'id' => $post->getId(),
            'status' => $post->status->value,
            'message' => $autoApprove
                ? 'Post created successfully'
                : 'Post submitted for moderation',
        ]);
    }

    /**
     * Update own post (authenticated).
     */
    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdatePostDTO $dto,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $post = $this->postRepository->find($id);

        if (!$post) {
            return $this->notFound('Post not found');
        }

        // Only owner or admin can edit
        if ($post->userId !== $user->getId() && $user->rank < 5) {
            return $this->error('You can only edit your own posts', 403);
        }

        $post->updateContent($dto->content);
        $this->postRepository->save($post);

        return $this->success(
            $this->normalizer->normalize($post, 'json', ['groups' => ['forum_post:read']])
        );
    }

    /**
     * Delete own post (authenticated).
     */
    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $post = $this->postRepository->find($id);

        if (!$post) {
            return $this->notFound('Post not found');
        }

        // Only owner or admin can delete
        if ($post->userId !== $user->getId() && $user->rank < 5) {
            return $this->error('You can only delete your own posts', 403);
        }

        $threadId = $post->threadId;

        $this->postRepository->remove($post);

        // Update thread stats
        $thread = $this->threadRepository->find($threadId);
        if ($thread) {
            $thread->decrementReplyCount();

            // Update last post info
            $lastPost = $this->postRepository->findLastInThread($threadId);
            if ($lastPost) {
                $thread->updateLastPost($lastPost->getId(), $lastPost->userId, $lastPost->createdAt);
            } else {
                $thread->updateLastPost(null, $thread->userId, $thread->createdAt);
            }

            $this->threadRepository->save($thread, false);

            // Update category stats
            $category = $this->categoryRepository->find($thread->categoryId);
            if ($category) {
                $category->decrementPostCount();
                $this->categoryRepository->save($category);
            }
        }

        return $this->success(['deleted' => true]);
    }

    // ==================== MODERATION ENDPOINTS ====================

    /**
     * Get posts pending moderation (mod+).
     */
    #[Route('/moderation/{page<\d+>}/{perPage<\d+>}', name: 'moderation_list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    #[IsGranted('PERMISSION_MODERATE_FORUM')]
    public function moderationList(int $page, int $perPage): JsonResponse
    {
        $result = $this->postRepository->findPendingPaginated($page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['forum_post:list', 'forum_post:admin']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
            'pendingCount' => $this->postRepository->countPending(),
        ]);
    }

    /**
     * Approve a post (mod+).
     */
    #[Route('/{id<\d+>}/approve', name: 'approve', methods: ['POST'])]
    #[IsGranted('PERMISSION_MODERATE_FORUM')]
    public function approve(int $id): JsonResponse
    {
        $post = $this->postRepository->find($id);

        if (!$post) {
            return $this->notFound('Post not found');
        }

        $wasApproved = $post->isApproved();
        $post->approve();
        $this->postRepository->save($post);

        // Update stats if newly approved
        if (!$wasApproved) {
            $thread = $this->threadRepository->find($post->threadId);
            if ($thread) {
                $thread->incrementReplyCount();
                $thread->updateLastPost($post->getId(), $post->userId, $post->createdAt);
                $this->threadRepository->save($thread, false);

                $category = $this->categoryRepository->find($thread->categoryId);
                if ($category) {
                    $category->incrementPostCount();
                    $this->categoryRepository->save($category);
                }
            }
        }

        return $this->success(['status' => 'approved']);
    }

    /**
     * Hide a post (mod+).
     */
    #[Route('/{id<\d+>}/hide', name: 'hide', methods: ['POST'])]
    #[IsGranted('PERMISSION_MODERATE_FORUM')]
    public function hide(int $id): JsonResponse
    {
        $post = $this->postRepository->find($id);

        if (!$post) {
            return $this->notFound('Post not found');
        }

        $wasApproved = $post->isApproved();
        $post->hide();
        $this->postRepository->save($post);

        // Update stats if was approved
        if ($wasApproved) {
            $thread = $this->threadRepository->find($post->threadId);
            if ($thread) {
                $thread->decrementReplyCount();
                $this->threadRepository->save($thread, false);

                $category = $this->categoryRepository->find($thread->categoryId);
                if ($category) {
                    $category->decrementPostCount();
                    $this->categoryRepository->save($category);
                }
            }
        }

        return $this->success(['status' => 'hidden']);
    }

    /**
     * Bulk moderate posts (mod+).
     */
    #[Route('/moderate/bulk', name: 'moderate_bulk', methods: ['POST'])]
    #[IsGranted('PERMISSION_MODERATE_FORUM')]
    public function moderateBulk(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $ids = $data['ids'] ?? [];
        $action = $data['action'] ?? null;

        if (empty($ids) || !in_array($action, ['approve', 'hide'], true)) {
            return $this->validationError(['ids' => 'IDs required', 'action' => 'Valid action required (approve/hide)']);
        }

        $count = 0;
        foreach ($ids as $id) {
            $post = $this->postRepository->find($id);
            if ($post) {
                match ($action) {
                    'approve' => $post->approve(),
                    'hide' => $post->hide(),
                };
                $this->postRepository->save($post, false);
                $count++;
            }
        }

        $this->postRepository->flush();

        return $this->success(['moderated' => $count]);
    }

    // ==================== VOTE ENDPOINTS ====================

    /**
     * Like a post (authenticated).
     */
    #[Route('/{id<\d+>}/like', name: 'like', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function like(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $post = $this->postRepository->find($id);

        if (!$post || !$post->isApproved()) {
            return $this->notFound('Post not found');
        }

        return $this->handleVote($id, $user->getId(), VoteType::LIKE);
    }

    /**
     * Dislike a post (authenticated).
     */
    #[Route('/{id<\d+>}/dislike', name: 'dislike', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function dislike(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $post = $this->postRepository->find($id);

        if (!$post || !$post->isApproved()) {
            return $this->notFound('Post not found');
        }

        return $this->handleVote($id, $user->getId(), VoteType::DISLIKE);
    }

    /**
     * Get user's vote on a post (authenticated).
     */
    #[Route('/{id<\d+>}/vote', name: 'get_vote', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getVote(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $post = $this->postRepository->find($id);

        if (!$post) {
            return $this->notFound('Post not found');
        }

        $existingVote = $this->voteRepository->findExistingVote($user->getId(), $id, VoteEntity::FORUM_COMMENT);
        $counts = $this->voteRepository->getVoteCountsForEntity($id, VoteEntity::FORUM_COMMENT);

        return $this->success([
            'likes' => $counts['likes'],
            'dislikes' => $counts['dislikes'],
            'user_vote' => $existingVote?->voteType->value,
        ]);
    }

    /**
     * Remove vote from a post (authenticated).
     */
    #[Route('/{id<\d+>}/vote', name: 'remove_vote', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function removeVote(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $post = $this->postRepository->find($id);

        if (!$post) {
            return $this->notFound('Post not found');
        }

        $existingVote = $this->voteRepository->findExistingVote($user->getId(), $id, VoteEntity::FORUM_COMMENT);

        if (!$existingVote) {
            return $this->error('No vote found', 404);
        }

        $this->voteRepository->remove($existingVote);
        $counts = $this->voteRepository->getVoteCountsForEntity($id, VoteEntity::FORUM_COMMENT);

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
    private function handleVote(int $postId, int $userId, VoteType $voteType): JsonResponse
    {
        $existingVote = $this->voteRepository->findExistingVote($userId, $postId, VoteEntity::FORUM_COMMENT);

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
            $vote = Vote::create($userId, $postId, VoteEntity::FORUM_COMMENT, $voteType);
            $this->voteRepository->save($vote);
            $message = $voteType === VoteType::LIKE ? 'Post liked' : 'Post disliked';
            $userVote = $voteType->value;
        }

        $counts = $this->voteRepository->getVoteCountsForEntity($postId, VoteEntity::FORUM_COMMENT);

        return $this->success([
            'message' => $message,
            'likes' => $counts['likes'],
            'dislikes' => $counts['dislikes'],
            'user_vote' => $userVote,
        ]);
    }
}
