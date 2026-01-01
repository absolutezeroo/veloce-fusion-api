<?php

declare(strict_types=1);

namespace App\Application\Authorization\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateHierarchyDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $parentRoleId,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $childRoleId,
    ) {}
}
