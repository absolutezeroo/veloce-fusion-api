<?php

declare(strict_types=1);

namespace App\Application\Setting\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateSettingDTO
{
    public function __construct(
        #[Assert\NotBlank]
        public string $key,

        #[Assert\NotBlank]
        public string $value,
    ) {}
}
