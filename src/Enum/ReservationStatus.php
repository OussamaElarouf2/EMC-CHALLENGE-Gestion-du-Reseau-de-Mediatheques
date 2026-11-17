<?php

declare(strict_types=1);

namespace App\Enum;

enum ReservationStatus: string
{
    case WAITING = 'WAITING';
    case AWAITING_TRANSFER = 'AWAITING_TRANSFER';
    case READY = 'READY';
    case FULFILLED = 'FULFILLED';
    case EXPIRED = 'EXPIRED';
    case CANCELLED = 'CANCELLED';
}
