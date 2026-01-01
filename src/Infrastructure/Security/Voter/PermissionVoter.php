<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Voter;

use App\Domain\User\Entity\User;
use App\Infrastructure\Security\Service\PermissionResolver;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Security Voter that checks user permissions based on rank and RBAC system.
 *
 * Usage in controllers:
 *   #[IsGranted('PERMISSION_create-article')]
 *   #[IsGranted('PERMISSION_view-all-settings')]
 *
 * Or programmatically:
 *   $this->denyAccessUnlessGranted('PERMISSION_manage-users');
 */
#[AutoconfigureTag('security.voter')]
final class PermissionVoter extends Voter
{
    private const PERMISSION_PREFIX = 'PERMISSION_';

    public function __construct(
        private readonly PermissionResolver $permissionResolver,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, self::PERMISSION_PREFIX);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            $vote?->addReason('User is not authenticated or not a valid User entity');
            return false;
        }

        // Extract permission name from attribute (e.g., "PERMISSION_create-article" -> "create-article")
        $permissionName = substr($attribute, strlen(self::PERMISSION_PREFIX));

        $hasPermission = $this->permissionResolver->hasPermission($user->rank, $permissionName);

        if (!$hasPermission) {
            $vote?->addReason(sprintf('User rank %d does not have permission "%s"', $user->rank, $permissionName));
        }

        return $hasPermission;
    }
}
