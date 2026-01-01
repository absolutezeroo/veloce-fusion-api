<?php

declare(strict_types=1);

namespace App\Application\Article\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateCommentDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 2000)]
        public string $content,
    ) {}
}
