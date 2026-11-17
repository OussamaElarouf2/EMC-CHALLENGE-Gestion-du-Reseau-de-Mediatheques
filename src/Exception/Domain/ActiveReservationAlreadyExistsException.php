<?php

declare(strict_types=1);

namespace App\Exception\Domain;

class ActiveReservationAlreadyExistsException extends DomainException
{
    public function __construct(string $membershipNumber, string $editionTitle)
    {
        parent::__construct(sprintf(
            'Member %s already has an active reservation for edition "%s".',
            $membershipNumber,
            $editionTitle
        ));
    }
}
