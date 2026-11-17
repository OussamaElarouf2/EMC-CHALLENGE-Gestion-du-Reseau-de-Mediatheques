<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\Loan;
use App\Entity\Reservation;
use App\Enum\CopyStatus;
use App\Enum\PenaltyReason;
use App\Enum\ReservationStatus;
use App\Enum\TransferStatus;
use App\Exception\Domain\InvalidTransferStateException;
use App\Repository\LoanRepository;
use App\Repository\PenaltyRepository;
use App\Repository\ReservationRepository;
use App\Service\LoanService;
use App\Service\PenaltyCalculator;
use App\Service\ReservationQueueService;
use App\Service\TransferService;
use App\Tests\Integration\BaseIntegrationTestCase;

class TransferServiceTest extends BaseIntegrationTestCase
{
    private TransferService $transferService;
    private LoanService $loanService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transferService = new TransferService($this->em, $this->clock);

        $penaltyCalculator = new PenaltyCalculator();
        $reservationQueueService = self::getContainer()->get(ReservationQueueService::class);
        $loanRepo = self::getContainer()->get(LoanRepository::class);
        $reservationRepo = self::getContainer()->get(ReservationRepository::class);
        $penaltyRepo = self::getContainer()->get(PenaltyRepository::class);

        $this->loanService = new LoanService(
            $this->em,
            $this->clock,
            $penaltyCalculator,
            $reservationQueueService,
            $loanRepo,
            $reservationRepo,
            $penaltyRepo
        );
    }

    public function test_transfer_workflow_updates_copy_branch_and_activates_pickup_ready(): void
    {
        $branchSource = $this->createBranch('Médiathèque Centrale', 'Site A');
        $branchTarget = $this->createBranch('Médiathèque Sud', 'Site B');
        $plan = $this->createPlan();
        $member = $this->createMember($plan, $branchTarget);

        $edition = $this->createEdition('Le Comte de Monte-Cristo');
        $copy = $this->createCopy($edition, $branchSource, CopyStatus::AVAILABLE);

        $reservation = new Reservation($edition, $member, $branchTarget, ReservationStatus::WAITING, $this->clock->now());
        $this->em->persist($reservation);
        $this->em->flush();

        // 1. Request transfer
        $transfer = $this->transferService->requestTransfer($copy, $branchSource, $branchTarget, $reservation);

        $this->assertSame(TransferStatus::REQUESTED, $transfer->getStatus());
        $this->assertSame(CopyStatus::IN_TRANSIT, $copy->getStatus());

        // 2. Ship transfer
        $this->clock->modify('+2 hours');
        $this->transferService->shipTransfer($transfer);

        $this->assertSame(TransferStatus::IN_TRANSIT, $transfer->getStatus());
        $this->assertNotNull($transfer->getShippedAt());

        // 3. Receive transfer at target branch
        $this->clock->modify('+3 hours');
        $this->transferService->receiveTransfer($transfer);

        $this->assertSame(TransferStatus::RECEIVED, $transfer->getStatus());
        $this->assertNotNull($transfer->getReceivedAt());

        // Copy branch is now updated to target branch
        $this->assertSame($branchTarget->getId(), $copy->getCurrentBranch()->getId());
        $this->assertSame(CopyStatus::HELD_FOR_PICKUP, $copy->getStatus());

        // Linked reservation is READY for pickup with a 5-day deadline
        $this->assertSame(ReservationStatus::READY, $reservation->getStatus());
        $this->assertSame($copy->getId(), $reservation->getAssignedCopy()->getId());
        $this->assertSame(
            $this->clock->now()->modify('+5 days')->format('Y-m-d H:i:s'),
            $reservation->getPickupDeadlineAt()->format('Y-m-d H:i:s')
        );
    }

    public function test_transfer_workflow_without_reservation_makes_copy_available(): void
    {
        $branchSource = $this->createBranch('Site A', 'Adresse A');
        $branchTarget = $this->createBranch('Site B', 'Adresse B');

        $edition = $this->createEdition('Les Misérables');
        $copy = $this->createCopy($edition, $branchSource, CopyStatus::AVAILABLE);

        // Logistics rebalancing without user reservation
        $transfer = $this->transferService->requestTransfer($copy, $branchSource, $branchTarget);
        $this->transferService->shipTransfer($transfer);
        $this->transferService->receiveTransfer($transfer);

        $this->assertSame($branchTarget->getId(), $copy->getCurrentBranch()->getId());
        $this->assertSame(CopyStatus::AVAILABLE, $copy->getStatus());
    }

    public function test_invalid_transfer_transition_throws_exception(): void
    {
        $branchSource = $this->createBranch('Site A');
        $branchTarget = $this->createBranch('Site B');
        $edition = $this->createEdition();
        $copy = $this->createCopy($edition, $branchSource);

        $transfer = $this->transferService->requestTransfer($copy, $branchSource, $branchTarget);
        $this->transferService->shipTransfer($transfer);
        $this->transferService->receiveTransfer($transfer);

        // Attempting to ship an already RECEIVED transfer must fail
        $this->expectException(InvalidTransferStateException::class);
        $this->expectExceptionMessage('Cannot ship transfer');

        $this->transferService->shipTransfer($transfer);
    }

    public function test_mark_copy_lost_removes_from_circulation_and_creates_penalty(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan();
        $member = $this->createMember($plan, $branch);
        $edition = $this->createEdition();
        $copy = $this->createCopy($edition, $branch);

        $loan = $this->loanService->borrow($member, $copy);

        // Member declares the book lost -> replacement fee of 150.00 MAD
        $penalty = $this->loanService->markLost($copy, $loan, '150.00');

        $this->assertSame(CopyStatus::LOST, $copy->getStatus());
        $this->assertNotNull($loan->getReturnedAt());
        $this->assertNull($loan->getActiveCopyId());

        $this->assertNotNull($penalty);
        $this->assertSame('150.00', $penalty->getAmount());
        $this->assertSame(PenaltyReason::LOST, $penalty->getReason());
        $this->assertFalse($penalty->isPaid());
    }

    public function test_mark_copy_damaged_removes_from_circulation(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan();
        $member = $this->createMember($plan, $branch);
        $edition = $this->createEdition();
        $copy = $this->createCopy($edition, $branch);

        $loan = $this->loanService->borrow($member, $copy);

        // Returned with water damage -> repair fee of 50.00 MAD
        $penalty = $this->loanService->markDamaged($copy, $loan, '50.00');

        $this->assertSame(CopyStatus::DAMAGED, $copy->getStatus());
        $this->assertNotNull($loan->getReturnedAt());

        $this->assertNotNull($penalty);
        $this->assertSame('50.00', $penalty->getAmount());
        $this->assertSame(PenaltyReason::DAMAGED, $penalty->getReason());
    }
}
