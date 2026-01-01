<?php

declare(strict_types=1);

namespace App\Application\Forum\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCategoryDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Category name is required')]
        #[Assert\Length(min: 2, max: 100, minMessage: 'Name must be at least 2 characters', maxMessage: 'Name cannot exceed 100 characters')]
        public string $name,

        #[Assert\Length(max: 500, maxMessage: 'Description cannot exceed 500 characters')]
        public ?string $description = null,

        #[Assert\Length(max: 50, maxMessage: 'Icon cannot exceed 50 characters')]
        public ?string $icon = null,

        #[Assert\PositiveOrZero(message: 'Parent ID must be positive')]
        public ?int $parentId = null,

        #[Assert\PositiveOrZero(message: 'Position must be positive or zero')]
        public int $position = 0,
    ) {}
}
