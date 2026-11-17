<?php

declare(strict_types=1);

namespace App\Exception\Domain;

class LoanAlreadyReturnedException extends DomainException
{
    public function __construct(int $loanId)
    {
        parent::__construct(sprintf(
            'Loan #%d has already been returned and cannot be modified.',
            $loanId
        ));
    }
}
