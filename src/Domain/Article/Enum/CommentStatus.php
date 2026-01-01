<?php

declare(strict_types=1);

namespace App\Domain\Article\Enum;

enum CommentStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SPAM = 'spam';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::SPAM => 'Spam',
        };
    }

    public function isVisible(): bool
    {
        return $this === self::APPROVED;
    }
}
