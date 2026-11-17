<?php

declare(strict_types=1);

namespace App\Exception\Domain;

use App\Enum\CopyStatus;

class CopyNotAvailableException extends DomainException
{
    public function __construct(string $barcode, CopyStatus $status)
    {
        parent::__construct(sprintf(
            'Copy %s is not available for loan (current status: %s).',
            $barcode,
            $status->value
        ));
    }
}
