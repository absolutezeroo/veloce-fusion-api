<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\UserChecker;

use App\Domain\Ban\Exception\UserBannedException;
use App\Domain\Ban\Repository\BanRepository;
use App\Domain\User\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Checks if a user is banned before allowing authentication.
 * This is called by Symfony Security during the authentication process.
 */
final readonly class BannedUserChecker implements UserCheckerInterface
{
    public function __construct(
        private BanRepository $banRepository,
    ) {}

    /**
     * Called before credentials are checked.
     * Use this to check for bans.
     */
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        $ban = $this->banRepository->findActiveBanByUserId($user->getId());

        if ($ban !== null) {
            throw new UserBannedException($ban, $this->buildBanMessage($ban));
        }
    }

    /**
     * Called after credentials are validated.
     */
    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Nothing to check post-auth for bans
    }

    private function buildBanMessage(\App\Domain\Ban\Entity\Ban $ban): string
    {
        if ($ban->isPermanent()) {
            return sprintf(
                'Your account has been permanently banned. Reason: %s',
                $ban->reason
            );
        }

        $expiresDate = date('Y-m-d H:i:s', $ban->expiresAt);

        return sprintf(
            'Your account has been banned until %s. Reason: %s',
            $expiresDate,
            $ban->reason
        );
    }
}
