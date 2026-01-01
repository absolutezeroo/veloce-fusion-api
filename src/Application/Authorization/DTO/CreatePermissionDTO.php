<?php

declare(strict_types=1);

namespace App\Application\Authorization\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreatePermissionDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        #[Assert\Regex(
            pattern: '/^[A-Z][A-Z0-9_]*$/',
            message: 'Permission name must be uppercase with underscores (e.g., MANAGE_USERS)'
        )]
        public string $name,

        #[Assert\Length(max: 1000)]
        public ?string $description = null,
    ) {}
}
