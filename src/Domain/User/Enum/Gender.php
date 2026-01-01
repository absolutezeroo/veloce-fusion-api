<?php

declare(strict_types=1);

namespace App\Domain\User\Enum;

enum Gender: string
{
    case MALE = 'M';
    case FEMALE = 'F';

    public function label(): string
    {
        return match ($this) {
            self::MALE => 'Male',
            self::FEMALE => 'Female',
        };
    }
}
