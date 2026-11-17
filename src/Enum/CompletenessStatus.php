<?php

declare(strict_types=1);

namespace App\Enum;

enum CompletenessStatus: string
{
    case COMPLETE = 'COMPLETE';
    case TO_COMPLETE = 'TO_COMPLETE';
}
