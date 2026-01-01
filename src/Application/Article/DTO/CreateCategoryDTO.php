<?php

declare(strict_types=1);

namespace App\Application\Article\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCategoryDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 100)]
        public string $name,

        #[Assert\Length(max: 500)]
        public ?string $description = null,

        #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Color must be a valid hex code (e.g., #FF5733)')]
        public ?string $color = null,

        #[Assert\PositiveOrZero]
        public int $sortOrder = 0,
    ) {}
}
