<?php

declare(strict_types=1);

namespace App\Application\Forum\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreatePostDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Post content is required')]
        #[Assert\Length(min: 2, minMessage: 'Content must be at least 2 characters')]
        public string $content,

        #[Assert\PositiveOrZero(message: 'Quoted post ID must be positive')]
        public ?int $quotedPostId = null,
    ) {}
}
