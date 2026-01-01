<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Authorization;

use App\Application\Authorization\DTO\AssignPermissionDTO;
use App\Application\Authorization\DTO\CreatePermissionDTO;
use App\Domain\Authorization\Entity\Permission;
use App\Domain\Authorization\Entity\RolePermission;
use App\Domain\Authorization\Repository\PermissionRepository;
use App\Domain\Authorization\Repository\RolePermissionRepository;
use App\Domain\Authorization\Repository\RoleRepository;
use App\Domain\User\Entity\User;
use App\Infrastructure\Security\Service\PermissionResolver;
use App\Presentation\Controller\Api\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/permissions', name: 'api_permissions_')]
final class PermissionController extends AbstractApiController
{
    public function __construct(
        private readonly PermissionRepository $permissionRepository,
        private readonly RoleRepository $roleRepository,
        private readonly RolePermissionRepository $rolePermissionRepository,
        private readonly PermissionResolver $permissionResolver,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Get current user's permissions.
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function myPermissions(#[CurrentUser] User $user): JsonResponse
    {
        $permissions = $this->permissionResolver->getPermissionsForRank($user->rank);

        return $this->success([
            'permissions' => $permissions,
            'rank' => $user->rank,
        ]);
    }

    /**
     * Get paginated list of permissions (admin only).
     */
    #[Route('/list/{page<\d+>}/{perPage<\d+>}', name: 'list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(int $page, int $perPage): JsonResponse
    {
        $result = $this->permissionRepository->getPaginated($page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['permission:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Create a new permission (admin only).
     */
    #[Route('/create', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(#[MapRequestPayload] CreatePermissionDTO $dto): JsonResponse
    {
        // Check if permission already exists
        $existing = $this->permissionRepository->findByName($dto->name);

        if ($existing) {
            return $this->error('Permission already exists', 422);
        }

        $permission = Permission::create($dto->name, $dto->description);
        $this->permissionRepository->save($permission);

        return $this->created(
            $this->normalizer->normalize($permission, 'json', ['groups' => ['permission:read']])
        );
    }

    /**
     * Assign a permission to a role (admin only).
     */
    #[Route('/assign', name: 'assign', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function assignToRole(#[MapRequestPayload] AssignPermissionDTO $dto): JsonResponse
    {
        // Check if role exists
        $role = $this->roleRepository->find($dto->roleId);

        if (!$role) {
            return $this->notFound('Role not found');
        }

        // Check if permission exists
        $permission = $this->permissionRepository->find($dto->permissionId);

        if (!$permission) {
            return $this->notFound('Permission not found');
        }

        // Check if already assigned
        $existing = $this->rolePermissionRepository->findByRoleAndPermission($dto->roleId, $dto->permissionId);

        if ($existing) {
            return $this->error('Permission is already assigned to this role', 422);
        }

        $rolePermission = RolePermission::create($dto->roleId, $dto->permissionId);
        $this->rolePermissionRepository->save($rolePermission);

        return $this->created(['assigned' => true]);
    }

    /**
     * Delete a permission (admin only).
     */
    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $permission = $this->permissionRepository->find($id);

        if (!$permission) {
            return $this->notFound('Permission not found');
        }

        $this->permissionRepository->remove($permission);

        return $this->success(['deleted' => true]);
    }
}
