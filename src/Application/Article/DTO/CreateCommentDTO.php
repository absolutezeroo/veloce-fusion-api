<?php

declare(strict_types=1);

namespace App\Application\Article\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCommentDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $articleId,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 2000)]
        public string $content,

        #[Assert\Positive]
        public ?int $parentId = null,
    ) {}
}
