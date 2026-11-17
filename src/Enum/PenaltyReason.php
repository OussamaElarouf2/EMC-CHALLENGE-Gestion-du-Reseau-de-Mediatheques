<?php

declare(strict_types=1);

namespace App\Enum;

enum PenaltyReason: string
{
    case LATE = 'LATE';
    case LOST = 'LOST';
    case DAMAGED = 'DAMAGED';
}
