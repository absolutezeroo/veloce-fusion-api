<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Ban;

use App\Application\Ban\DTO\CreateBanDTO;
use App\Domain\Ban\Entity\Ban;
use App\Domain\Ban\Repository\BanRepository;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepository;
use App\Presentation\Controller\Api\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/bans', name: 'api_bans_')]
#[IsGranted('ROLE_ADMIN')]
final class BanController extends AbstractApiController
{
    public function __construct(
        private readonly BanRepository $banRepository,
        private readonly UserRepository $userRepository,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Get paginated list of active bans.
     */
    #[Route('/list/{page<\d+>}/{perPage<\d+>}', name: 'list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function list(int $page, int $perPage): JsonResponse
    {
        $result = $this->banRepository->getActiveBansPaginated($page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['ban:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Get ban details.
     */
    #[Route('/{id<\d+>}', name: 'get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $ban = $this->banRepository->find($id);

        if (!$ban) {
            return $this->notFound('Ban not found');
        }

        return $this->success(
            $this->normalizer->normalize($ban, 'json', ['groups' => ['ban:read']])
        );
    }

    /**
     * Check if a user is banned.
     */
    #[Route('/check/{userId<\d+>}', name: 'check', methods: ['GET'])]
    public function check(int $userId): JsonResponse
    {
        $ban = $this->banRepository->findActiveBanByUserId($userId);

        return $this->success([
            'is_banned' => $ban !== null,
            'ban' => $ban ? $this->normalizer->normalize($ban, 'json', ['groups' => ['ban:read']]) : null,
        ]);
    }

    /**
     * Create a new ban.
     */
    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(
        #[MapRequestPayload] CreateBanDTO $dto,
        #[CurrentUser] User $staffUser,
    ): JsonResponse {
        // Check if user exists
        $targetUser = $this->userRepository->find($dto->userId);

        if (!$targetUser) {
            return $this->notFound('User not found');
        }

        // Check if user already has an active ban
        $existingBan = $this->banRepository->findActiveBanByUserId($dto->userId);

        if ($existingBan) {
            return $this->error('User already has an active ban', 422);
        }

        $ban = Ban::create(
            userId: $dto->userId,
            staffUserId: $staffUser->getId(),
            reason: $dto->reason,
            type: $dto->getBanType(),
            expiresAt: $dto->getExpiresAt(),
            ip: $dto->ip ?? '',
            machineId: $dto->machineId ?? '',
        );

        $this->banRepository->save($ban);

        return $this->created(
            $this->normalizer->normalize($ban, 'json', ['groups' => ['ban:read']])
        );
    }

    /**
     * Remove/unban a user (delete ban record).
     */
    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $ban = $this->banRepository->find($id);

        if (!$ban) {
            return $this->notFound('Ban not found');
        }

        $this->banRepository->remove($ban);

        return $this->success(['deleted' => true]);
    }

    /**
     * Get ban history for a user.
     */
    #[Route('/history/{userId<\d+>}', name: 'history', methods: ['GET'])]
    public function history(int $userId): JsonResponse
    {
        $bans = $this->banRepository->findAllByUserId($userId);

        return $this->success(
            $this->normalizer->normalize($bans, 'json', ['groups' => ['ban:list']])
        );
    }
}
