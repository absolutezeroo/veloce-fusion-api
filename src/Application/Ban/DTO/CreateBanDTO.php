<?php

declare(strict_types=1);

namespace App\Application\Ban\DTO;

use App\Domain\Ban\Enum\BanType;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateBanDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $userId,

        #[Assert\NotBlank]
        public string $reason,

        #[Assert\Choice(callback: [BanType::class, 'cases'])]
        public string $type = 'user',

        #[Assert\PositiveOrZero]
        public int $duration = 0, // 0 = permanent, otherwise seconds

        public ?string $ip = null,

        public ?string $machineId = null,
    ) {}

    public function getBanType(): BanType
    {
        return BanType::tryFrom($this->type) ?? BanType::USER;
    }

    public function getExpiresAt(): int
    {
        if ($this->duration === 0) {
            return 0; // Permanent
        }

        return time() + $this->duration;
    }
}
