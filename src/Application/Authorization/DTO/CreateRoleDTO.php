<?php

declare(strict_types=1);

namespace App\Application\Authorization\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateRoleDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        public string $name,

        #[Assert\Length(max: 1000)]
        public ?string $description = null,
    ) {}
}
