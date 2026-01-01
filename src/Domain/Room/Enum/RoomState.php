<?php

declare(strict_types=1);

namespace App\Domain\Room\Enum;

enum RoomState: string
{
    case OPEN = 'open';
    case LOCKED = 'locked';
    case PASSWORD = 'password';
    case INVISIBLE = 'invisible';

    public function isPublic(): bool
    {
        return $this === self::OPEN;
    }

    public function requiresPassword(): bool
    {
        return $this === self::PASSWORD;
    }

    public function isAccessible(): bool
    {
        return $this !== self::INVISIBLE;
    }
}
