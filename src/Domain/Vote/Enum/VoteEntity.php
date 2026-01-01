<?php

declare(strict_types=1);

namespace App\Domain\Vote\Enum;

/**
 * Enum representing voteable entity types.
 */
enum VoteEntity: int
{
    case ARTICLE = 1;
    case ARTICLE_COMMENT = 2;
    case FORUM = 3;
    case FORUM_COMMENT = 4;
    case GUESTBOOK = 5;
    case PHOTO = 6;

    public function label(): string
    {
        return match ($this) {
            self::ARTICLE => 'Article',
            self::ARTICLE_COMMENT => 'Article Comment',
            self::FORUM => 'Forum Post',
            self::FORUM_COMMENT => 'Forum Comment',
            self::GUESTBOOK => 'Guestbook Entry',
            self::PHOTO => 'Photo',
        };
    }
}
