<?php

declare(strict_types=1);

namespace App\Application\Authorization\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AssignRoleDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $roleId,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $rankId,
    ) {}
}
