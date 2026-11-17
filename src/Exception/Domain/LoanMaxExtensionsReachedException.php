<?php

declare(strict_types=1);

namespace App\Exception\Domain;

class LoanMaxExtensionsReachedException extends DomainException
{
    public function __construct(int $loanId, int $maxExtensions)
    {
        parent::__construct(sprintf(
            'Cannot extend loan #%d: maximum extension limit of %d has already been reached.',
            $loanId,
            $maxExtensions
        ));
    }
}
