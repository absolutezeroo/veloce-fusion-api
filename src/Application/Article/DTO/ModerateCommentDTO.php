<?php

declare(strict_types=1);

namespace App\Application\Article\DTO;

use App\Domain\Article\Enum\CommentStatus;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ModerateCommentDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['approve', 'reject', 'spam'])]
        public string $action,
    ) {}

    public function getStatus(): CommentStatus
    {
        return match ($this->action) {
            'approve' => CommentStatus::APPROVED,
            'reject' => CommentStatus::REJECTED,
            'spam' => CommentStatus::SPAM,
            default => CommentStatus::PENDING,
        };
    }
}
