<?php

declare(strict_types=1);

namespace App\Domain\Forum\Enum;

enum PostStatus: string
{
    case APPROVED = 'approved';
    case PENDING = 'pending';
    case HIDDEN = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::APPROVED => 'Approved',
            self::PENDING => 'Pending',
            self::HIDDEN => 'Hidden',
        };
    }

    public function isVisible(): bool
    {
        return $this === self::APPROVED;
    }
}
