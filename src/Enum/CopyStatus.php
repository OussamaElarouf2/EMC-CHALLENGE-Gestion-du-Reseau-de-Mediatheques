<?php

declare(strict_types=1);

namespace App\Enum;

enum CopyStatus: string
{
    case AVAILABLE = 'AVAILABLE';
    case ON_LOAN = 'ON_LOAN';
    case HELD_FOR_PICKUP = 'HELD_FOR_PICKUP';
    case IN_TRANSIT = 'IN_TRANSIT';
    case LOST = 'LOST';
    case DAMAGED = 'DAMAGED';
}
