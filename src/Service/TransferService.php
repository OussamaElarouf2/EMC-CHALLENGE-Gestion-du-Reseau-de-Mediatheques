<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Branch;
use App\Entity\Copy;
use App\Entity\Reservation;
use App\Entity\Transfer;
use App\Enum\CopyStatus;
use App\Enum\ReservationStatus;
use App\Enum\TransferStatus;
use App\Exception\Domain\InvalidTransferStateException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

class TransferService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    public function requestTransfer(Copy $copy, Branch $source, Branch $target, ?Reservation $reservation = null): Transfer
    {
        if ($source->getId() !== null && $target->getId() !== null && $source->getId() === $target->getId()) {
            throw new \InvalidArgumentException('Source and target branch cannot be the same for an inter-site transfer.');
        }

        $now = $this->clock->now();
        $transfer = new Transfer($copy, $source, $target, TransferStatus::REQUESTED, $now, $reservation);

        $copy->setStatus(CopyStatus::IN_TRANSIT);

        $this->em->persist($transfer);
        $this->em->flush();

        return $transfer;
    }

    public function shipTransfer(Transfer $transfer): Transfer
    {
        if ($transfer->getStatus() !== TransferStatus::REQUESTED) {
            throw new InvalidTransferStateException($transfer->getId() ?? 0, $transfer->getStatus(), 'ship');
        }

        $now = $this->clock->now();
        $transfer->setStatus(TransferStatus::IN_TRANSIT);
        $transfer->setShippedAt($now);
        $transfer->getCopy()->setStatus(CopyStatus::IN_TRANSIT);

        $this->em->flush();

        return $transfer;
    }

    public function receiveTransfer(Transfer $transfer): Transfer
    {
        if (!in_array($transfer->getStatus(), [TransferStatus::REQUESTED, TransferStatus::IN_TRANSIT], true)) {
            throw new InvalidTransferStateException($transfer->getId() ?? 0, $transfer->getStatus(), 'receive');
        }

        $now = $this->clock->now();
        $transfer->setStatus(TransferStatus::RECEIVED);
        $transfer->setReceivedAt($now);

        $copy = $transfer->getCopy();
        $copy->setCurrentBranch($transfer->getTargetBranch());

        $reservation = $transfer->getReservation();
        if ($reservation !== null && in_array($reservation->getStatus(), [ReservationStatus::WAITING, ReservationStatus::READY], true)) {
            $reservation->setAssignedCopy($copy);
            $reservation->setStatus(ReservationStatus::READY);
            $reservation->setPickupDeadlineAt($now->modify('+5 days'));
            $copy->setStatus(CopyStatus::HELD_FOR_PICKUP);
        } else {
            $copy->setStatus(CopyStatus::AVAILABLE);
        }

        $this->em->flush();

        return $transfer;
    }
}
