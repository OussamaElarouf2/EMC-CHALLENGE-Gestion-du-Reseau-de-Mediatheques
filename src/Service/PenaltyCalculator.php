<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Loan;

class PenaltyCalculator
{
    /**
     * Calculates the number of full overdue days for a loan.
     * A loan is overdue only once the return timestamp passes the end of the due date (23:59:59).
     */
    public function calculateLateDays(Loan $loan, \DateTimeImmutable $returnedAt): int
    {
        $dueEndOfDay = $loan->getDueDate()->setTime(23, 59, 59);

        if ($returnedAt <= $dueEndOfDay) {
            return 0;
        }

        $secondsLate = $returnedAt->getTimestamp() - $dueEndOfDay->getTimestamp();

        return (int) ceil($secondsLate / 86400);
    }

    /**
     * Calculates the late penalty amount in MAD.
     * Returns null if the loan was returned on time.
     */
    public function calculateLatePenalty(Loan $loan, \DateTimeImmutable $returnedAt): ?string
    {
        $daysLate = $this->calculateLateDays($loan, $returnedAt);

        if ($daysLate <= 0) {
            return null;
        }

        $dailyRate = $loan->getMember()->getPlan()->getPenaltyRatePerDay();

        return bcmul((string) $daysLate, $dailyRate, 2);
    }
}
