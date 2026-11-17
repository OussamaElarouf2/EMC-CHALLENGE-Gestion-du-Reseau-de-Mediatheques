<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Copy;
use App\Entity\Loan;
use App\Entity\Member;
use App\Entity\Penalty;
use App\Enum\CopyStatus;
use App\Enum\PenaltyReason;
use App\Exception\Domain\CopyNotAvailableException;
use App\Exception\Domain\LoanAlreadyReturnedException;
use App\Exception\Domain\LoanMaxExtensionsReachedException;
use App\Exception\Domain\LoanOverdueException;
use App\Exception\Domain\LoanQuotaExceededException;
use App\Exception\Domain\ReservationQueueNotEmptyException;
use App\Exception\Domain\SubscriptionExpiredException;
use App\Exception\Domain\UnpaidPenaltyException;
use App\Repository\LoanRepository;
use App\Repository\PenaltyRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

class LoanService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly PenaltyCalculator $penaltyCalculator,
        private readonly ReservationQueueService $reservationQueueService,
        private readonly LoanRepository $loanRepo,
        private readonly ReservationRepository $reservationRepo,
        private readonly PenaltyRepository $penaltyRepo,
    ) {
    }

    public function borrow(Member $member, Copy $copy): Loan
    {
        return $this->em->wrapInTransaction(function () use ($member, $copy): Loan {
            $now = $this->clock->now();

            // 1. Subscription validity
            if ($member->getSubscriptionEndsAt() < $now) {
                throw new SubscriptionExpiredException($member->getMembershipNumber(), $member->getSubscriptionEndsAt());
            }

            // 2. Unpaid penalty check
            if ($this->penaltyRepo->hasUnpaidPenalties($member)) {
                $total = $this->penaltyRepo->getTotalUnpaidAmount($member);
                throw new UnpaidPenaltyException($member->getMembershipNumber(), $total);
            }

            // 3. Plan simultaneous loans quota
            $activeLoans = $this->loanRepo->countActiveLoansByMember($member);
            $maxLoans = $member->getPlan()->getMaxSimultaneousLoans();
            if ($activeLoans >= $maxLoans) {
                throw new LoanQuotaExceededException($maxLoans, $activeLoans);
            }

            // 4. Copy availability
            if ($copy->getStatus() !== CopyStatus::AVAILABLE) {
                throw new CopyNotAvailableException($copy->getBarcode(), $copy->getStatus());
            }

            // 5. Due date calculation according to plan duration
            $durationDays = $member->getPlan()->getLoanDurationDays();
            $dueDate = $now->modify(sprintf('+%d days', $durationDays))->setTime(0, 0, 0);

            $loan = new Loan($copy, $member, $now, $dueDate);
            $copy->setStatus(CopyStatus::ON_LOAN);

            $this->em->persist($loan);
            $this->em->flush();

            return $loan;
        });
    }

    public function return(Loan $loan): ?Penalty
    {
        return $this->em->wrapInTransaction(function () use ($loan): ?Penalty {
            if ($loan->getReturnedAt() !== null) {
                throw new LoanAlreadyReturnedException($loan->getId() ?? 0);
            }

            $now = $this->clock->now();
            $loan->markReturned($now);

            // Calculate late penalty if overdue
            $penaltyAmount = $this->penaltyCalculator->calculateLatePenalty($loan, $now);
            $penalty = null;

            if ($penaltyAmount !== null) {
                $penalty = new Penalty($loan, $penaltyAmount, PenaltyReason::LATE, $now, false);
                $this->em->persist($penalty);
            }

            // Release copy or hand over to waiting reservations
            $this->reservationQueueService->handleCopyReturned($loan->getCopy());

            $this->em->flush();

            return $penalty;
        });
    }

    public function extend(Loan $loan): Loan
    {
        if ($loan->getReturnedAt() !== null) {
            throw new LoanAlreadyReturnedException($loan->getId() ?? 0);
        }

        $now = $this->clock->now();
        $dueEndOfDay = $loan->getDueDate()->setTime(23, 59, 59);

        // Cannot extend if already overdue
        if ($now > $dueEndOfDay) {
            throw new LoanOverdueException($loan->getId() ?? 0, $loan->getDueDate());
        }

        // Cannot extend if extension limit reached
        $maxExtensions = $loan->getMember()->getPlan()->getMaxExtensions();
        if ($loan->getExtensionCount() >= $maxExtensions) {
            throw new LoanMaxExtensionsReachedException($loan->getId() ?? 0, $maxExtensions);
        }

        // Cannot extend if someone is waiting in the reservation queue
        $waitingCount = $this->reservationRepo->countWaitingForEdition($loan->getCopy()->getEdition());
        if ($waitingCount > 0) {
            throw new ReservationQueueNotEmptyException(
                $loan->getId() ?? 0,
                $loan->getCopy()->getEdition()->getTitle(),
                $waitingCount
            );
        }

        $extensionDays = $loan->getMember()->getPlan()->getExtensionDurationDays();
        $newDueDate = $loan->getDueDate()->modify(sprintf('+%d days', $extensionDays));

        $loan->setDueDate($newDueDate);
        $loan->incrementExtensionCount();

        $this->em->flush();

        return $loan;
    }

    public function markLost(Copy $copy, ?Loan $loan = null, ?string $penaltyAmount = null): ?Penalty
    {
        return $this->em->wrapInTransaction(function () use ($copy, $loan, $penaltyAmount): ?Penalty {
            $copy->setStatus(CopyStatus::LOST);
            $activeLoan = $loan ?? $this->loanRepo->findActiveLoanByCopy($copy);

            $penalty = null;
            $now = $this->clock->now();

            if ($activeLoan !== null) {
                $activeLoan->markReturned($now);

                if ($penaltyAmount !== null) {
                    $penalty = new Penalty($activeLoan, $penaltyAmount, PenaltyReason::LOST, $now, false);
                    $this->em->persist($penalty);
                }
            }

            $this->em->flush();

            return $penalty;
        });
    }

    public function markDamaged(Copy $copy, ?Loan $loan = null, ?string $penaltyAmount = null): ?Penalty
    {
        return $this->em->wrapInTransaction(function () use ($copy, $loan, $penaltyAmount): ?Penalty {
            $copy->setStatus(CopyStatus::DAMAGED);
            $activeLoan = $loan ?? $this->loanRepo->findActiveLoanByCopy($copy);

            $penalty = null;
            $now = $this->clock->now();

            if ($activeLoan !== null) {
                $activeLoan->markReturned($now);

                if ($penaltyAmount !== null) {
                    $penalty = new Penalty($activeLoan, $penaltyAmount, PenaltyReason::DAMAGED, $now, false);
                    $this->em->persist($penalty);
                }
            }

            $this->em->flush();

            return $penalty;
        });
    }
}
