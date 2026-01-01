<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Article;

use App\Application\Article\DTO\CreateCommentDTO;
use App\Application\Article\DTO\ModerateCommentDTO;
use App\Application\Article\DTO\UpdateCommentDTO;
use App\Domain\Article\Entity\Comment;
use App\Domain\Article\Enum\CommentStatus;
use App\Domain\Article\Repository\ArticleRepository;
use App\Domain\Article\Repository\CommentRepository;
use App\Domain\User\Entity\User;
use App\Presentation\Controller\Api\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/comments', name: 'api_comments_')]
final class CommentController extends AbstractApiController
{
    public function __construct(
        private readonly CommentRepository $commentRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Get comments for an article (public).
     */
    #[Route('/article/{articleId<\d+>}/{page<\d+>}/{perPage<\d+>}', name: 'by_article', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function byArticle(int $articleId, int $page, int $perPage): JsonResponse
    {
        $result = $this->commentRepository->findApprovedByArticle($articleId, $page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['comment:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get replies for a comment (public).
     */
    #[Route('/{commentId<\d+>}/replies', name: 'replies', methods: ['GET'])]
    public function replies(int $commentId): JsonResponse
    {
        $replies = $this->commentRepository->findApprovedReplies($commentId);

        return $this->success(
            $this->normalizer->normalize($replies, 'json', ['groups' => ['comment:list']])
        );
    }

    /**
     * Create a comment (authenticated users).
     */
    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateCommentDTO $dto,
        #[CurrentUser] User $user,
    ): JsonResponse {
        // Check article exists
        $article = $this->articleRepository->find($dto->articleId);

        if (!$article || !$article->isPublished()) {
            return $this->notFound('Article not found');
        }

        // If reply, check parent exists
        if ($dto->parentId !== null) {
            $parent = $this->commentRepository->find($dto->parentId);
            if (!$parent || !$parent->isApproved()) {
                return $this->notFound('Parent comment not found');
            }
        }

        // Auto-approve for staff (rank >= 3)
        $autoApprove = $user->rank >= 3;

        $comment = Comment::create(
            articleId: $dto->articleId,
            userId: $user->getId(),
            content: $dto->content,
            parentId: $dto->parentId,
            autoApprove: $autoApprove,
        );

        $this->commentRepository->save($comment);

        return $this->created([
            'id' => $comment->getId(),
            'status' => $comment->status->value,
            'message' => $autoApprove
                ? 'Comment posted successfully'
                : 'Comment submitted for moderation',
        ]);
    }

    /**
     * Update own comment (authenticated users).
     */
    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateCommentDTO $dto,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $comment = $this->commentRepository->find($id);

        if (!$comment) {
            return $this->notFound('Comment not found');
        }

        // Only owner can edit (or admin via moderation endpoint)
        if ($comment->userId !== $user->getId()) {
            return $this->error('You can only edit your own comments', 403);
        }

        $comment->updateContent($dto->content);
        $this->commentRepository->save($comment);

        return $this->success(
            $this->normalizer->normalize($comment, 'json', ['groups' => ['comment:read']])
        );
    }

    /**
     * Delete own comment (authenticated users).
     */
    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $comment = $this->commentRepository->find($id);

        if (!$comment) {
            return $this->notFound('Comment not found');
        }

        // Owner or admin can delete
        if ($comment->userId !== $user->getId() && $user->rank < 5) {
            return $this->error('You can only delete your own comments', 403);
        }

        $this->commentRepository->remove($comment);

        return $this->success(['deleted' => true]);
    }

    // ==================== MODERATION ENDPOINTS ====================

    /**
     * Get comments for moderation (mod+).
     */
    #[Route('/moderation/{page<\d+>}/{perPage<\d+>}', name: 'moderation_list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    #[IsGranted('PERMISSION_MODERATE_COMMENTS')]
    public function moderationList(int $page, int $perPage, Request $request): JsonResponse
    {
        $status = $request->query->get('status') ? CommentStatus::tryFrom($request->query->get('status')) : null;

        $result = $this->commentRepository->findForModeration($page, $perPage, $status);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['comment:list', 'comment:admin']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
            'pendingCount' => $this->commentRepository->countPending(),
        ]);
    }

    /**
     * Moderate a comment (approve/reject/spam) (mod+).
     */
    #[Route('/{id<\d+>}/moderate', name: 'moderate', methods: ['POST'])]
    #[IsGranted('PERMISSION_MODERATE_COMMENTS')]
    public function moderate(
        int $id,
        #[MapRequestPayload] ModerateCommentDTO $dto,
    ): JsonResponse {
        $comment = $this->commentRepository->find($id);

        if (!$comment) {
            return $this->notFound('Comment not found');
        }

        match ($dto->action) {
            'approve' => $comment->approve(),
            'reject' => $comment->reject(),
            'spam' => $comment->markAsSpam(),
            default => null,
        };

        $this->commentRepository->save($comment);

        return $this->success([
            'id' => $comment->getId(),
            'status' => $comment->status->value,
        ]);
    }

    /**
     * Bulk moderate comments (mod+).
     */
    #[Route('/moderate/bulk', name: 'moderate_bulk', methods: ['POST'])]
    #[IsGranted('PERMISSION_MODERATE_COMMENTS')]
    public function moderateBulk(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $ids = $data['ids'] ?? [];
        $action = $data['action'] ?? null;

        if (empty($ids) || !in_array($action, ['approve', 'reject', 'spam'], true)) {
            return $this->validationError(['ids' => 'IDs required', 'action' => 'Valid action required']);
        }

        $count = 0;
        foreach ($ids as $id) {
            $comment = $this->commentRepository->find($id);
            if ($comment) {
                match ($action) {
                    'approve' => $comment->approve(),
                    'reject' => $comment->reject(),
                    'spam' => $comment->markAsSpam(),
                };
                $this->commentRepository->save($comment, false);
                $count++;
            }
        }

        $this->commentRepository->flush();

        return $this->success(['moderated' => $count]);
    }
}
