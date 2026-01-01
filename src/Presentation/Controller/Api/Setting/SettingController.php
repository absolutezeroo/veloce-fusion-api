<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Setting;

use App\Application\Setting\DTO\UpdateSettingDTO;
use App\Domain\Setting\Repository\SettingRepository;
use App\Presentation\Controller\Api\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/settings', name: 'api_settings_')]
final class SettingController extends AbstractApiController
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Get a setting by key (public endpoint).
     */
    #[Route('/get', name: 'get', methods: ['POST'])]
    public function get(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $key = $data['key'] ?? null;

        if (!$key) {
            return $this->validationError(['key' => 'Key is required']);
        }

        $setting = $this->settingRepository->findByKey($key);

        if (!$setting) {
            return $this->notFound('Setting not found');
        }

        return $this->success(
            $this->normalizer->normalize($setting, 'json', ['groups' => ['setting:read']])
        );
    }

    /**
     * Get paginated settings list (admin only).
     */
    #[Route('/list/{page<\d+>}/{perPage<\d+>}', name: 'list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(int $page, int $perPage): JsonResponse
    {
        $result = $this->settingRepository->getPaginated($page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['setting:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Update a setting (admin only).
     */
    #[Route('/set', name: 'set', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function set(#[MapRequestPayload] UpdateSettingDTO $dto): JsonResponse
    {
        $setting = $this->settingRepository->findByKey($dto->key);

        if (!$setting) {
            return $this->notFound('Setting not found');
        }

        $setting->updateValue($dto->value);
        $this->settingRepository->save($setting);

        return $this->success(
            $this->normalizer->normalize($setting, 'json', ['groups' => ['setting:read']])
        );
    }
}
