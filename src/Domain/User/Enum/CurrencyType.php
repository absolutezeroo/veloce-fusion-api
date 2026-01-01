<?php

declare(strict_types=1);

namespace App\Domain\User\Enum;

enum CurrencyType: int
{
    case DUCKETS = 0;
    case DIAMONDS = 5;
    case POINTS = 101;
    case PIXELS = 103;

    public function label(): string
    {
        return match ($this) {
            self::DUCKETS => 'Duckets',
            self::DIAMONDS => 'Diamonds',
            self::POINTS => 'Points',
            self::PIXELS => 'Pixels',
        };
    }
}
