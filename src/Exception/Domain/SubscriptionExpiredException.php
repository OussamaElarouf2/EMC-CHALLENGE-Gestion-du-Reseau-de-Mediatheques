<?php

declare(strict_types=1);

namespace App\Exception\Domain;

class SubscriptionExpiredException extends DomainException
{
    public function __construct(string $membershipNumber, \DateTimeImmutable $expiredAt)
    {
        parent::__construct(sprintf(
            'Cannot borrow: subscription for member %s expired on %s.',
            $membershipNumber,
            $expiredAt->format('Y-m-d')
        ));
    }
}
