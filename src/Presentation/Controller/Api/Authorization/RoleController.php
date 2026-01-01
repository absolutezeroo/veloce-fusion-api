<?php

declare(strict_types=1);

namespace App\Presentation\Controller\Api\Authorization;

use App\Application\Authorization\DTO\AssignRoleDTO;
use App\Application\Authorization\DTO\CreateHierarchyDTO;
use App\Application\Authorization\DTO\CreateRoleDTO;
use App\Domain\Authorization\Entity\Role;
use App\Domain\Authorization\Entity\RoleHierarchy;
use App\Domain\Authorization\Entity\RoleRank;
use App\Domain\Authorization\Repository\RoleHierarchyRepository;
use App\Domain\Authorization\Repository\RoleRankRepository;
use App\Domain\Authorization\Repository\RoleRepository;
use App\Infrastructure\Security\Service\PermissionResolver;
use App\Presentation\Controller\Api\AbstractApiController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/roles', name: 'api_roles_')]
#[IsGranted('ROLE_ADMIN')]
final class RoleController extends AbstractApiController
{
    public function __construct(
        private readonly RoleRepository $roleRepository,
        private readonly RoleRankRepository $roleRankRepository,
        private readonly RoleHierarchyRepository $roleHierarchyRepository,
        private readonly PermissionResolver $permissionResolver,
        private readonly NormalizerInterface $normalizer,
    ) {}

    /**
     * Get paginated list of roles.
     */
    #[Route('/list/{page<\d+>}/{perPage<\d+>}', name: 'list', defaults: ['page' => 1, 'perPage' => 20], methods: ['GET'])]
    public function list(int $page, int $perPage): JsonResponse
    {
        $result = $this->roleRepository->getPaginated($page, $perPage);

        return $this->success([
            'items' => $this->normalizer->normalize($result['items'], 'json', ['groups' => ['role:list']]),
            'pagination' => [
                'total' => $result['total'],
                'page' => $result['page'],
                'perPage' => $result['perPage'],
                'lastPage' => $result['lastPage'],
            ],
        ]);
    }

    /**
     * Create a new role.
     */
    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] CreateRoleDTO $dto): JsonResponse
    {
        // Check if role already exists
        $existing = $this->roleRepository->findByName($dto->name);

        if ($existing) {
            return $this->error('Role already exists', 422);
        }

        $role = Role::create($dto->name, $dto->description);
        $this->roleRepository->save($role);

        return $this->created(
            $this->normalizer->normalize($role, 'json', ['groups' => ['role:read']])
        );
    }

    /**
     * Assign a role to a rank.
     */
    #[Route('/assign', name: 'assign', methods: ['POST'])]
    public function assignToRank(#[MapRequestPayload] AssignRoleDTO $dto): JsonResponse
    {
        // Check if role exists
        $role = $this->roleRepository->find($dto->roleId);

        if (!$role) {
            return $this->notFound('Role not found');
        }

        // Check if already assigned
        $existing = $this->roleRankRepository->findByRoleAndRank($dto->roleId, $dto->rankId);

        if ($existing) {
            return $this->error('Role is already assigned to this rank', 422);
        }

        $roleRank = RoleRank::create($dto->roleId, $dto->rankId);
        $this->roleRankRepository->save($roleRank);

        // Clear cache for this rank
        $this->permissionResolver->clearCache($dto->rankId);

        return $this->created(['assigned' => true]);
    }

    /**
     * Create a role hierarchy (parent inherits from child).
     */
    #[Route('/hierarchy/create', name: 'hierarchy_create', methods: ['POST'])]
    public function createHierarchy(#[MapRequestPayload] CreateHierarchyDTO $dto): JsonResponse
    {
        // Check if roles exist
        $parentRole = $this->roleRepository->find($dto->parentRoleId);
        $childRole = $this->roleRepository->find($dto->childRoleId);

        if (!$parentRole || !$childRole) {
            return $this->notFound('One or both roles not found');
        }

        // Check for cycle
        if ($this->roleHierarchyRepository->wouldCreateCycle($dto->parentRoleId, $dto->childRoleId)) {
            return $this->error('This hierarchy would create a cycle', 422);
        }

        // Check if already exists
        if ($this->roleHierarchyRepository->hierarchyExists($dto->parentRoleId, $dto->childRoleId)) {
            return $this->error('This hierarchy already exists', 422);
        }

        $hierarchy = RoleHierarchy::create($dto->parentRoleId, $dto->childRoleId);
        $this->roleHierarchyRepository->save($hierarchy);

        return $this->created(
            $this->normalizer->normalize($hierarchy, 'json', ['groups' => ['role_hierarchy:read']])
        );
    }

    /**
     * Delete a role.
     */
    #[Route('/{id<\d+>}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $role = $this->roleRepository->find($id);

        if (!$role) {
            return $this->notFound('Role not found');
        }

        $this->roleRepository->remove($role);

        return $this->success(['deleted' => true]);
    }
}
