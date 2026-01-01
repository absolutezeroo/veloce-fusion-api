<?php

declare(strict_types=1);

namespace App\Domain\Article\Enum;

enum ArticleStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SCHEDULED => 'Scheduled',
            self::PUBLISHED => 'Published',
            self::ARCHIVED => 'Archived',
        };
    }

    public function isVisible(): bool
    {
        return $this === self::PUBLISHED;
    }

    public function canEdit(): bool
    {
        return $this !== self::ARCHIVED;
    }
}
