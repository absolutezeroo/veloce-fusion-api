<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Article;

use App\Application\Article\DTO\CreateCategoryDTO;
use App\Domain\Article\Entity\Category;
use App\Domain\Article\Repository\CategoryRepository;
use App\Presentation\Controller\Api\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/categories', name: 'api_categories_')]
final class CategoryController extends AbstractApiController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Get all active categories (public).
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $categories = $this->categoryRepository->findAllActive();

        return $this->success(
            $this->normalizer->normalize($categories, 'json', ['groups' => ['category:list']])
        );
    }

    /**
     * Get single category by slug (public).
     */
    #[Route('/{slug}', name: 'show', methods: ['GET'], priority: -1)]
    public function show(string $slug): JsonResponse
    {
        $category = $this->categoryRepository->findBySlug($slug);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        return $this->success(
            $this->normalizer->normalize($category, 'json', ['groups' => ['category:read']])
        );
    }

    // ==================== ADMIN ENDPOINTS ====================

    /**
     * Create a category (admin only).
     */
    #[Route('/create', name: 'create', methods: ['POST'])]
    #[IsGranted('PERMISSION_MANAGE_CATEGORIES')]
    public function create(#[MapRequestPayload] CreateCategoryDTO $dto): JsonResponse
    {
        // Check slug uniqueness
        $existing = $this->categoryRepository->findBySlug(
            (new \Symfony\Component\String\Slugger\AsciiSlugger())->slug($dto->name)->lower()->toString()
        );

        if ($existing) {
            return $this->error('A category with this name already exists', 422);
        }

        $category = Category::create($dto->name, $dto->description, $dto->color);
        $category->updateSortOrder($dto->sortOrder);

        $this->categoryRepository->save($category);

        return $this->created(
            $this->normalizer->normalize($category, 'json', ['groups' => ['category:read']])
        );
    }

    /**
     * Update a category (admin only).
     */
    #[Route('/{id<\d+>}', name: 'update', methods: ['PUT'])]
    #[IsGranted('PERMISSION_MANAGE_CATEGORIES')]
    public function update(
        int $id,
        #[MapRequestPayload] CreateCategoryDTO $dto,
    ): JsonResponse {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        $category->updateName($dto->name);
        $category->updateDescription($dto->description);
        $category->updateColor($dto->color);
        $category->updateSortOrder($dto->sortOrder);

        $this->categoryRepository->save($category);

        return $this->success(
            $this->normalizer->normalize($category, 'json', ['groups' => ['category:read']])
        );
    }

    /**
     * Toggle category active status (admin only).
     */
    #[Route('/{id<\d+>}/toggle', name: 'toggle', methods: ['POST'])]
    #[IsGranted('PERMISSION_MANAGE_CATEGORIES')]
    public function toggle(int $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        $category->isActive ? $category->deactivate() : $category->activate();

        $this->categoryRepository->save($category);

        return $this->success([
            'id' => $category->getId(),
            'isActive' => $category->isActive,
        ]);
    }

    /**
     * Delete a category (admin only).
     */
    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('PERMISSION_MANAGE_CATEGORIES')]
    public function delete(int $id): JsonResponse
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            return $this->notFound('Category not found');
        }

        $this->categoryRepository->remove($category);

        return $this->success(['deleted' => true]);
    }
}
