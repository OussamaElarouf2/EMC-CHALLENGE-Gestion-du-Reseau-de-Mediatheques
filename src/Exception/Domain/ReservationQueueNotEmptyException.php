<?php

declare(strict_types=1);

namespace App\Exception\Domain;

class ReservationQueueNotEmptyException extends DomainException
{
    public function __construct(int $loanId, string $editionTitle, int $waitingCount)
    {
        parent::__construct(sprintf(
            'Cannot extend loan #%d: %d reservation(s) are currently waiting for edition "%s".',
            $loanId,
            $waitingCount,
            $editionTitle
        ));
    }
}
