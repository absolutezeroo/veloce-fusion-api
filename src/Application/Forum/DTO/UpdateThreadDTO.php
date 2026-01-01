<?php

declare(strict_types=1);

namespace App\Application\Forum\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateThreadDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Thread title is required')]
        #[Assert\Length(min: 5, max: 200, minMessage: 'Title must be at least 5 characters', maxMessage: 'Title cannot exceed 200 characters')]
        public string $title,

        #[Assert\NotBlank(message: 'Thread content is required')]
        #[Assert\Length(min: 10, minMessage: 'Content must be at least 10 characters')]
        public string $content,
    ) {}
}
