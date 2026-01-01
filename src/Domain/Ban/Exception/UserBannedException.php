<?php

declare(strict_types=1);

namespace App\Domain\Ban\Exception;

use App\Domain\Ban\Entity\Ban;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class UserBannedException extends CustomUserMessageAccountStatusException
{
    public function __construct(
        private readonly Ban $ban,
        string $message = 'Your account has been banned.',
    ) {
        parent::__construct($message);
    }

    public function getBan(): Ban
    {
        return $this->ban;
    }

    public function getMessageData(): array
    {
        return [
            'reason' => $this->ban->reason,
            'expires_at' => $this->ban->isPermanent() ? null : $this->ban->expiresAt,
            'is_permanent' => $this->ban->isPermanent(),
        ];
    }
}
