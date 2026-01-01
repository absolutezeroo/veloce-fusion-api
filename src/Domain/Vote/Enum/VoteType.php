<?php

declare(strict_types=1);

namespace App\Domain\Vote\Enum;

/**
 * Enum representing vote types.
 */
enum VoteType: int
{
    case LIKE = 1;
    case DISLIKE = 2;

    public function label(): string
    {
        return match ($this) {
            self::LIKE => 'Like',
            self::DISLIKE => 'Dislike',
        };
    }

    public function isLike(): bool
    {
        return $this === self::LIKE;
    }

    public function isDislike(): bool
    {
        return $this === self::DISLIKE;
    }
}
