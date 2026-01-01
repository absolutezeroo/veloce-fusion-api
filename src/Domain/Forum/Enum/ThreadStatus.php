<?php

declare(strict_types=1);

namespace App\Domain\Forum\Enum;

enum ThreadStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case LOCKED = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::CLOSED => 'Closed',
            self::LOCKED => 'Locked',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::OPEN;
    }

    public function canReply(): bool
    {
        return $this === self::OPEN;
    }
}
