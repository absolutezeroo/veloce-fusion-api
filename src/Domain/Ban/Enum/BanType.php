<?php

declare(strict_types=1);

namespace App\Domain\Ban\Enum;

enum BanType: string
{
    case USER = 'user';
    case IP = 'ip';
    case MACHINE = 'machine';
    case SUPER = 'super'; // All three combined
}
