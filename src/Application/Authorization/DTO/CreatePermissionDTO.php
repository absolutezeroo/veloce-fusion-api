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
            pattern: '/^[a-z0-9\-]+$/',
            message: 'Permission name can only contain lowercase letters, numbers, and hyphens'
        )]
        public string $name,

        #[Assert\Length(max: 1000)]
        public ?string $description = null,
    ) {}
}
