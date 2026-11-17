<?php

declare(strict_types=1);

namespace App\Exception\Domain;

class LoanOverdueException extends DomainException
{
    public function __construct(int $loanId, \DateTimeImmutable $dueDate)
    {
        parent::__construct(sprintf(
            'Cannot extend loan #%d: loan is already overdue since %s.',
            $loanId,
            $dueDate->format('Y-m-d')
        ));
    }
}
