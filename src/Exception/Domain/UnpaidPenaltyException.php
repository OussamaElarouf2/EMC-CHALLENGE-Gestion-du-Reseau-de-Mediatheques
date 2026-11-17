<?php

declare(strict_types=1);

namespace App\Exception\Domain;

class UnpaidPenaltyException extends DomainException
{
    public function __construct(string $membershipNumber, string $totalUnpaid)
    {
        parent::__construct(sprintf(
            'Cannot borrow: member %s has unpaid penalties totaling %s MAD.',
            $membershipNumber,
            $totalUnpaid
        ));
    }
}
