<?php

declare(strict_types=1);

namespace App\Exception\Domain;

use App\Enum\TransferStatus;

class InvalidTransferStateException extends DomainException
{
    public function __construct(int $transferId, TransferStatus $currentStatus, string $expectedAction)
    {
        parent::__construct(sprintf(
            'Cannot %s transfer #%d: current status is "%s".',
            $expectedAction,
            $transferId,
            $currentStatus->value
        ));
    }
}
