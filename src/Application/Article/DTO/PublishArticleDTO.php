<?php

declare(strict_types=1);

namespace App\Application\Article\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PublishArticleDTO
{
    public function __construct(
        #[Assert\Choice(choices: ['publish', 'schedule', 'draft', 'archive'])]
        public string $action = 'publish',

        #[Assert\DateTime]
        public ?string $scheduledAt = null,
    ) {}

    public function getScheduledDate(): ?\DateTimeImmutable
    {
        if ($this->scheduledAt === null) {
            return null;
        }

        return new \DateTimeImmutable($this->scheduledAt);
    }
}
