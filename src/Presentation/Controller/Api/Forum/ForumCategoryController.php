<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Forum;

use App\Application\Forum\DTO\CreateCategoryDTO;
use App\Application\Forum\DTO\UpdateCategoryDTO;
use App\Domain\Forum\Entity\ForumCategory;
use App\Domain\Forum\Repository\ForumCategoryRepository;
use App\Presentation\Controller\Api\AbstractApiController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/forum/categories', name: 'api_forum_categories_')]
final class ForumCategoryController extends AbstractApiController
{
    public function __construct(
        private readonly ForumCategoryRepository $categoryRepository,
        private readonly NormalizerInterface $normalizer,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Get all categories (hierarchical).
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $categories = $this->categoryRepository->findRootCategories();

        return $this->success(
            $this->normalizer->normalize($categories, 'json', ['groups' => ['forum_category:read']])
        );
    }

    /**
     * Get single category by ID.
     */
    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $category = $this->categoryRepository->findWithChildren($id);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        return $this->success(
            $this->normalizer->normalize($category, 'json', ['groups' => ['forum_category:read']])
        );
    }

    /**
     * Get category by slug.
     */
    #[Route('/slug/{slug}', name: 'by_slug', methods: ['GET'])]
    public function bySlug(string $slug): JsonResponse
    {
        $category = $this->categoryRepository->findBySlug($slug);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        return $this->success(
            $this->normalizer->normalize($category, 'json', ['groups' => ['forum_category:read']])
        );
    }

    /**
     * Get categories for dropdown/select.
     */
    #[Route('/dropdown', name: 'dropdown', methods: ['GET'])]
    public function dropdown(): JsonResponse
    {
        $categories = $this->categoryRepository->findForDropdown();

        return $this->success($categories);
    }

    // ==================== ADMIN ENDPOINTS ====================

    /**
     * Create a new category (admin).
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('PERMISSION_MANAGE_FORUM')]
    public function create(
        #[MapRequestPayload] CreateCategoryDTO $dto,
    ): JsonResponse {
        // Check parent exists if provided
        $parent = null;
        if ($dto->parentId !== null) {
            $parent = $this->categoryRepository->find($dto->parentId);
            if (!$parent) {
                return $this->notFound('Parent category not found');
            }
        }

        // Get next position if not provided
        $position = $dto->position;
        if ($position === 0) {
            $position = $this->categoryRepository->getNextPosition($dto->parentId);
        }

        $category = ForumCategory::create(
            name: $dto->name,
            description: $dto->description,
            icon: $dto->icon,
            parent: $parent,
            position: $position,
        );

        $this->categoryRepository->save($category);

        return $this->created(
            $this->normalizer->normalize($category, 'json', ['groups' => ['forum_category:read']])
        );
    }

    /**
     * Update a category (admin).
     */
    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    #[IsGranted('PERMISSION_MANAGE_FORUM')]
    public function update(
        int $id,
        #[MapRequestPayload] UpdateCategoryDTO $dto,
    ): JsonResponse {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        $category->updateName($dto->name);
        $category->updateDescription($dto->description);
        $category->updateIcon($dto->icon);

        if ($dto->position !== null) {
            $category->setPosition($dto->position);
        }

        $this->categoryRepository->save($category);

        return $this->success(
            $this->normalizer->normalize($category, 'json', ['groups' => ['forum_category:read']])
        );
    }

    /**
     * Delete a category (admin).
     */
    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('PERMISSION_MANAGE_FORUM')]
    public function delete(int $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        // Check if category has threads
        if ($category->threadCount > 0) {
            return $this->error('Cannot delete category with threads. Move or delete threads first.', 400);
        }

        $this->categoryRepository->remove($category);

        return $this->success(['deleted' => true]);
    }

    /**
     * Lock a category (admin).
     */
    #[Route('/{id<\d+>}/lock', name: 'lock', methods: ['POST'])]
    #[IsGranted('PERMISSION_MANAGE_FORUM')]
    public function lock(int $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        $category->lock();
        $this->categoryRepository->save($category);

        return $this->success(['locked' => true]);
    }

    /**
     * Unlock a category (admin).
     */
    #[Route('/{id<\d+>}/unlock', name: 'unlock', methods: ['POST'])]
    #[IsGranted('PERMISSION_MANAGE_FORUM')]
    public function unlock(int $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        $category->unlock();
        $this->categoryRepository->save($category);

        return $this->success(['locked' => false]);
    }

    /**
     * Reorder categories (admin).
     */
    #[Route('/reorder', name: 'reorder', methods: ['POST'])]
    #[IsGranted('PERMISSION_MANAGE_FORUM')]
    public function reorder(
        #[MapRequestPayload] array $data,
    ): JsonResponse {
        $order = $data['order'] ?? [];

        if (empty($order)) {
            return $this->validationError(['order' => 'Order array is required']);
        }

        foreach ($order as $position => $categoryId) {
            $category = $this->categoryRepository->find($categoryId);
            if ($category) {
                $category->setPosition($position);
                $this->categoryRepository->save($category, false);
            }
        }

        $this->entityManager->flush();

        return $this->success(['reordered' => true]);
    }
}
