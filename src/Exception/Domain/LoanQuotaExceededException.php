<?php

declare(strict_types=1);

namespace App\Exception\Domain;

class LoanQuotaExceededException extends DomainException
{
    public function __construct(int $maxLoans, int $currentLoans)
    {
        parent::__construct(sprintf(
            'Loan quota exceeded: plan allows a maximum of %d simultaneous loans, member currently has %d.',
            $maxLoans,
            $currentLoans
        ));
    }
}
