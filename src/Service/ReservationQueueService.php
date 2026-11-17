<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Branch;
use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\Member;
use App\Entity\Reservation;
use App\Enum\CopyStatus;
use App\Enum\ReservationStatus;
use App\Exception\Domain\ActiveReservationAlreadyExistsException;
use App\Exception\Domain\SubscriptionExpiredException;
use App\Exception\Domain\UnpaidPenaltyException;
use App\Repository\CopyRepository;
use App\Repository\PenaltyRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

class ReservationQueueService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly ReservationRepository $reservationRepo,
        private readonly CopyRepository $copyRepo,
        private readonly PenaltyRepository $penaltyRepo,
        private readonly TransferService $transferService,
    ) {
    }

    public function reserve(Member $member, Edition $edition, Branch $pickupBranch): Reservation
    {
        $now = $this->clock->now();

        // 1. Subscription validity check
        if ($member->getSubscriptionEndsAt() < $now) {
            throw new SubscriptionExpiredException($member->getMembershipNumber(), $member->getSubscriptionEndsAt());
        }

        // 2. Unpaid penalty check
        if ($this->penaltyRepo->hasUnpaidPenalties($member)) {
            $total = $this->penaltyRepo->getTotalUnpaidAmount($member);
            throw new UnpaidPenaltyException($member->getMembershipNumber(), $total);
        }

        // 3. Existing active reservation check
        if ($this->reservationRepo->findActiveByMemberAndEdition($member, $edition) !== null) {
            throw new ActiveReservationAlreadyExistsException($member->getMembershipNumber(), $edition->getTitle());
        }

        $activeKey = ($member->getId() !== null && $edition->getId() !== null)
            ? Reservation::computeActiveKey($member->getId(), $edition->getId())
            : null;

        $reservation = new Reservation($edition, $member, $pickupBranch, ReservationStatus::WAITING, $now, $activeKey);

        // 4. Check for any currently available copy in the network
        $availableCopies = $this->copyRepo->findBy(['edition' => $edition, 'status' => CopyStatus::AVAILABLE]);

        if (count($availableCopies) > 0) {
            // Prefer a copy already present at the pickup branch
            $localCopy = null;
            $remoteCopy = null;

            foreach ($availableCopies as $candidate) {
                if ($candidate->getCurrentBranch()->getId() === $pickupBranch->getId()) {
                    $localCopy = $candidate;
                    break;
                }
                $remoteCopy ??= $candidate;
            }

            if ($localCopy !== null) {
                $reservation->setAssignedCopy($localCopy);
                $reservation->setStatus(ReservationStatus::READY);
                $reservation->setPickupDeadlineAt($now->modify('+5 days'));
                $localCopy->setStatus(CopyStatus::HELD_FOR_PICKUP);
            } elseif ($remoteCopy !== null) {
                $reservation->setAssignedCopy($remoteCopy);
                $this->transferService->requestTransfer($remoteCopy, $remoteCopy->getCurrentBranch(), $pickupBranch, $reservation);
                $remoteCopy->setStatus(CopyStatus::IN_TRANSIT);
            }
        }

        $this->em->persist($reservation);
        $this->em->flush();

        return $reservation;
    }

    public function handleCopyReturned(Copy $copy): ?Reservation
    {
        $next = $this->reservationRepo->findFirstWaitingForEdition($copy->getEdition());

        if ($next === null) {
            $copy->setStatus(CopyStatus::AVAILABLE);
            $this->em->flush();
            return null;
        }

        $now = $this->clock->now();
        $next->setAssignedCopy($copy);

        if ($copy->getCurrentBranch()->getId() === $next->getPickupBranch()->getId()) {
            $next->setStatus(ReservationStatus::READY);
            $next->setPickupDeadlineAt($now->modify('+5 days'));
            $copy->setStatus(CopyStatus::HELD_FOR_PICKUP);
        } else {
            $this->transferService->requestTransfer($copy, $copy->getCurrentBranch(), $next->getPickupBranch(), $next);
            $next->setStatus(ReservationStatus::WAITING);
            $copy->setStatus(CopyStatus::IN_TRANSIT);
        }

        $this->em->flush();

        return $next;
    }

    public function expireReservations(): int
    {
        $now = $this->clock->now();
        $expiredList = $this->reservationRepo->findExpiredReadyReservations($now);

        foreach ($expiredList as $reservation) {
            $reservation->setStatus(ReservationStatus::EXPIRED);
            $copy = $reservation->getAssignedCopy();
            $reservation->setAssignedCopy(null);

            if ($copy !== null) {
                $this->reallocateCopyOrMakeAvailable($copy);
            }
        }

        $this->em->flush();

        return count($expiredList);
    }

    public function cancel(Reservation $reservation): void
    {
        if (in_array($reservation->getStatus(), [ReservationStatus::CANCELLED, ReservationStatus::EXPIRED, ReservationStatus::FULFILLED], true)) {
            return;
        }

        $copy = $reservation->getAssignedCopy();
        $reservation->setStatus(ReservationStatus::CANCELLED);
        $reservation->setAssignedCopy(null);

        if ($copy !== null) {
            $this->reallocateCopyOrMakeAvailable($copy);
        }

        $this->em->flush();
    }

    private function reallocateCopyOrMakeAvailable(Copy $copy): void
    {
        $next = $this->reservationRepo->findFirstWaitingForEdition($copy->getEdition());

        if ($next === null) {
            $copy->setStatus(CopyStatus::AVAILABLE);
            return;
        }

        $now = $this->clock->now();
        $next->setAssignedCopy($copy);

        if ($copy->getCurrentBranch()->getId() === $next->getPickupBranch()->getId()) {
            $next->setStatus(ReservationStatus::READY);
            $next->setPickupDeadlineAt($now->modify('+5 days'));
            $copy->setStatus(CopyStatus::HELD_FOR_PICKUP);
        } else {
            $this->transferService->requestTransfer($copy, $copy->getCurrentBranch(), $next->getPickupBranch(), $next);
            $next->setStatus(ReservationStatus::WAITING);
            $copy->setStatus(CopyStatus::IN_TRANSIT);
        }
    }
}
