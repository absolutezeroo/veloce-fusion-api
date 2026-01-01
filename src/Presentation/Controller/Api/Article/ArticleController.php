<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Article;

use App\Application\Article\DTO\CreateArticleDTO;
use App\Application\Article\DTO\PublishArticleDTO;
use App\Application\Article\DTO\UpdateArticleDTO;
use App\Domain\Article\Entity\Article;
use App\Domain\Article\Enum\ArticleStatus;
use App\Domain\Article\Repository\ArticleRepository;
use App\Domain\Article\Repository\CategoryRepository;
use App\Domain\Article\Repository\TagRepository;
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

#[Route('/articles', name: 'api_articles_')]
final class ArticleController extends AbstractApiController
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TagRepository $tagRepository,
        private readonly VoteRepository $voteRepository,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Get published articles (public).
     */
    #[Route('/list/{page<\d+>}/{perPage<\d+>}', name: 'list', defaults: ['page' => 1, 'perPage' => 10], methods: ['GET'])]
    public function list(int $page, int $perPage, Request $request): JsonResponse
    {
        $categoryId = $request->query->get('category') ? (int) $request->query->get('category') : null;

        $result = $this->articleRepository->findPublishedPaginated($page, $perPage, $categoryId);

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
     * Get pinned articles (public).
     */
    #[Route('/pinned', name: 'pinned', methods: ['GET'])]
    public function pinned(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', '5');
        $articles = $this->articleRepository->findPinned($limit);

        return $this->success(
            $this->normalizer->normalize($articles, 'json', ['groups' => ['article:list']])
        );
    }

    /**
     * Get featured articles (public).
     */
    #[Route('/featured', name: 'featured', methods: ['GET'])]
    public function featured(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', '3');
        $articles = $this->articleRepository->findFeatured($limit);

        return $this->success(
            $this->normalizer->normalize($articles, 'json', ['groups' => ['article:list']])
        );
    }

    /**
     * Search articles (public).
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $page = (int) $request->query->get('page', '1');
        $perPage = (int) $request->query->get('perPage', '10');

        if (strlen($query) < 3) {
            return $this->validationError(['q' => 'Query must be at least 3 characters']);
        }

        $result = $this->articleRepository->search($query, $page, $perPage);

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
     * Get single article by slug (public).
     */
    #[Route('/{slug}', name: 'show', methods: ['GET'], priority: -1)]
    public function show(string $slug): JsonResponse
    {
        $article = $this->articleRepository->findPublishedBySlug($slug);

        if (!$article) {
            return $this->notFound('Article not found');
        }

        // Increment view count
        $article->incrementViewCount();
        $this->articleRepository->save($article);

        return $this->success(
            $this->normalizer->normalize($article, 'json', ['groups' => ['article:read']])
        );
    }

    /**
     * Get articles by tag (public).
     */
    #[Route('/tag/{tagSlug}/{page<\d+>}/{perPage<\d+>}', name: 'by_tag', defaults: ['page' => 1, 'perPage' => 10], methods: ['GET'])]
    public function byTag(string $tagSlug, int $page, int $perPage): JsonResponse
    {
        $result = $this->articleRepository->findByTagSlug($tagSlug, $page, $perPage);

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

    // ==================== ADMIN ENDPOINTS ====================

    /**
     * Get all articles for admin (includes drafts, scheduled, archived).
     */
    #[Route('/admin/list/{page<\d+>}/{perPage<\d+>}', name: 'admin_list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    #[IsGranted('PERMISSION_VIEW_ARTICLES')]
    public function adminList(int $page, int $perPage, Request $request): JsonResponse
    {
        $status = $request->query->get('status') ? ArticleStatus::tryFrom($request->query->get('status')) : null;
        $categoryId = $request->query->get('category') ? (int) $request->query->get('category') : null;
        $authorId = $request->query->get('author') ? (int) $request->query->get('author') : null;

        $result = $this->articleRepository->findAllPaginated($page, $perPage, $status, $categoryId, $authorId);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['article:admin']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get single article for editing (admin).
     */
    #[Route('/admin/{id<\d+>}', name: 'admin_show', methods: ['GET'])]
    #[IsGranted('PERMISSION_EDIT_ARTICLE')]
    public function adminShow(int $id): JsonResponse
    {
        $article = $this->articleRepository->find($id);

        if (!$article) {
            return $this->notFound('Article not found');
        }

        return $this->success(
            $this->normalizer->normalize($article, 'json', ['groups' => ['article:admin', 'article:read']])
        );
    }

    /**
     * Create a new article (staff+).
     */
    #[Route('/create', name: 'create', methods: ['POST'])]
    #[IsGranted('PERMISSION_CREATE_ARTICLE')]
    public function create(
        #[MapRequestPayload] CreateArticleDTO $dto,
        #[CurrentUser] User $user,
    ): JsonResponse {
        // Check slug uniqueness
        $existingSlug = $this->articleRepository->findBySlug(
            (new \Symfony\Component\String\Slugger\AsciiSlugger())->slug($dto->title)->lower()->toString()
        );

        if ($existingSlug) {
            return $this->error('An article with a similar title already exists', 422);
        }

        $article = Article::create(
            title: $dto->title,
            description: $dto->description,
            content: $dto->content,
            authorId: $user->getId(),
        );

        $article->updateImage($dto->image);
        $article->updateThumbnail($dto->thumbnail);
        $article->updateMeta($dto->metaTitle, $dto->metaDescription);

        if ($dto->isPinned) {
            $article->pin();
        }

        if ($dto->isFeatured) {
            $article->feature();
        }

        // Category
        if ($dto->categoryId) {
            $category = $this->categoryRepository->find($dto->categoryId);
            if ($category) {
                $article->updateCategory($category);
            }
        }

        // Tags
        if ($dto->tags) {
            foreach ($dto->tags as $tagName) {
                $tag = $this->tagRepository->findOrCreate($tagName);
                $article->addTag($tag);
            }
        }

        $this->articleRepository->save($article);

        return $this->created(
            $this->normalizer->normalize($article, 'json', ['groups' => ['article:admin']])
        );
    }

    /**
     * Update an article (staff+).
     */
    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    #[IsGranted('PERMISSION_EDIT_ARTICLE')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateArticleDTO $dto,
    ): JsonResponse {
        $article = $this->articleRepository->find($id);

        if (!$article) {
            return $this->notFound('Article not found');
        }

        if (!$article->status->canEdit()) {
            return $this->error('Archived articles cannot be edited', 422);
        }

        if ($dto->title !== null) {
            $article->updateTitle($dto->title);
            $article->generateSlug();
        }

        if ($dto->description !== null) {
            $article->updateDescription($dto->description);
        }

        if ($dto->content !== null) {
            $article->updateContent($dto->content);
        }

        if ($dto->image !== null) {
            $article->updateImage($dto->image);
        }

        if ($dto->thumbnail !== null) {
            $article->updateThumbnail($dto->thumbnail);
        }

        if ($dto->metaTitle !== null || $dto->metaDescription !== null) {
            $article->updateMeta(
                $dto->metaTitle ?? $article->metaTitle,
                $dto->metaDescription ?? $article->metaDescription
            );
        }

        if ($dto->isPinned !== null) {
            $dto->isPinned ? $article->pin() : $article->unpin();
        }

        if ($dto->isFeatured !== null) {
            $dto->isFeatured ? $article->feature() : $article->unfeature();
        }

        if ($dto->categoryId !== null) {
            $category = $this->categoryRepository->find($dto->categoryId);
            $article->updateCategory($category);
        }

        if ($dto->tags !== null) {
            $article->clearTags();
            foreach ($dto->tags as $tagName) {
                $tag = $this->tagRepository->findOrCreate($tagName);
                $article->addTag($tag);
            }
        }

        $this->articleRepository->save($article);

        return $this->success(
            $this->normalizer->normalize($article, 'json', ['groups' => ['article:admin']])
        );
    }

    /**
     * Publish/Schedule/Archive an article (mod+).
     */
    #[Route('/{id<\d+>}/publish', name: 'publish', methods: ['POST'])]
    #[IsGranted('PERMISSION_PUBLISH_ARTICLE')]
    public function publish(
        int $id,
        #[MapRequestPayload] PublishArticleDTO $dto,
    ): JsonResponse {
        $article = $this->articleRepository->find($id);

        if (!$article) {
            return $this->notFound('Article not found');
        }

        match ($dto->action) {
            'publish' => $article->publish(),
            'schedule' => $dto->getScheduledDate()
                ? $article->schedule($dto->getScheduledDate())
                : $article->publish(),
            'draft' => $article->toDraft(),
            'archive' => $article->archive(),
            default => null,
        };

        $this->articleRepository->save($article);

        return $this->success([
            'status' => $article->status->value,
            'publishedAt' => $article->publishedAt?->format('c'),
        ]);
    }

    /**
     * Delete an article (admin only).
     */
    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('PERMISSION_DELETE_ARTICLE')]
    public function delete(int $id): JsonResponse
    {
        $article = $this->articleRepository->find($id);

        if (!$article) {
            return $this->notFound('Article not found');
        }

        $this->articleRepository->remove($article);

        return $this->success(['deleted' => true]);
    }

    // ==================== VOTE ENDPOINTS ====================

    /**
     * Like an article (authenticated).
     */
    #[Route('/{slug}/like', name: 'like', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function like(
        string $slug,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $article = $this->articleRepository->findPublishedBySlug($slug);

        if (!$article) {
            return $this->notFound('Article not found');
        }

        return $this->handleVote($article->getId(), $user->getId(), VoteType::LIKE);
    }

    /**
     * Dislike an article (authenticated).
     */
    #[Route('/{slug}/dislike', name: 'dislike', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function dislike(
        string $slug,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $article = $this->articleRepository->findPublishedBySlug($slug);

        if (!$article) {
            return $this->notFound('Article not found');
        }

        return $this->handleVote($article->getId(), $user->getId(), VoteType::DISLIKE);
    }

    /**
     * Remove vote from an article (authenticated).
     */
    #[Route('/{slug}/vote', name: 'remove_vote', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function removeVote(
        string $slug,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $article = $this->articleRepository->findPublishedBySlug($slug);

        if (!$article) {
            return $this->notFound('Article not found');
        }

        $existingVote = $this->voteRepository->findExistingVote($user->getId(), $article->getId(), VoteEntity::ARTICLE);

        if (!$existingVote) {
            return $this->error('No vote found', 404);
        }

        $this->voteRepository->remove($existingVote);

        $counts = $this->voteRepository->getVoteCountsForEntity($article->getId(), VoteEntity::ARTICLE);

        return $this->success([
            'message' => 'Vote removed',
            'likes' => $counts['likes'],
            'dislikes' => $counts['dislikes'],
            'user_vote' => null,
        ]);
    }

    /**
     * Get user's vote on an article (authenticated).
     */
    #[Route('/{slug}/vote', name: 'get_vote', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getVote(
        string $slug,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $article = $this->articleRepository->findPublishedBySlug($slug);

        if (!$article) {
            return $this->notFound('Article not found');
        }

        $existingVote = $this->voteRepository->findExistingVote($user->getId(), $article->getId(), VoteEntity::ARTICLE);
        $counts = $this->voteRepository->getVoteCountsForEntity($article->getId(), VoteEntity::ARTICLE);

        return $this->success([
            'likes' => $counts['likes'],
            'dislikes' => $counts['dislikes'],
            'user_vote' => $existingVote?->voteType->value,
        ]);
    }

    /**
     * Handle vote logic (like/dislike with toggle).
     */
    private function handleVote(int $articleId, int $userId, VoteType $voteType): JsonResponse
    {
        $existingVote = $this->voteRepository->findExistingVote($userId, $articleId, VoteEntity::ARTICLE);

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
            $vote = Vote::create($userId, $articleId, VoteEntity::ARTICLE, $voteType);
            $this->voteRepository->save($vote);
            $message = $voteType === VoteType::LIKE ? 'Article liked' : 'Article disliked';
            $userVote = $voteType->value;
        }

        $counts = $this->voteRepository->getVoteCountsForEntity($articleId, VoteEntity::ARTICLE);

        return $this->success([
            'message' => $message,
            'likes' => $counts['likes'],
            'dislikes' => $counts['dislikes'],
            'user_vote' => $userVote,
        ]);
    }
}
