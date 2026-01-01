<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Article;

use App\Domain\Article\Repository\TagRepository;
use App\Presentation\Controller\Api\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/tags', name: 'api_tags_')]
final class TagController extends AbstractApiController
{
    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Get popular tags (public).
     */
    #[Route('/popular', name: 'popular', methods: ['GET'])]
    public function popular(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', '20');
        $tags = $this->tagRepository->findPopular($limit);

        return $this->success(
            $this->normalizer->normalize($tags, 'json', ['groups' => ['tag:list']])
        );
    }

    /**
     * Search tags (for autocomplete).
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', '10');

        if (strlen($query) < 2) {
            return $this->success([]);
        }

        $tags = $this->tagRepository->searchByName($query, $limit);

        return $this->success(
            $this->normalizer->normalize($tags, 'json', ['groups' => ['tag:list']])
        );
    }
}
