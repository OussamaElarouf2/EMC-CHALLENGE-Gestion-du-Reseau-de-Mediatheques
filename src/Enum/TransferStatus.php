<?php

declare(strict_types=1);

namespace App\Enum;

enum TransferStatus: string
{
    case REQUESTED = 'REQUESTED';
    case IN_TRANSIT = 'IN_TRANSIT';
    case RECEIVED = 'RECEIVED';
    case CANCELLED = 'CANCELLED';
}
