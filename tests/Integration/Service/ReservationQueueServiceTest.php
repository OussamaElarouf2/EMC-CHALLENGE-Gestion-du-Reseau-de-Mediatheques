<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Enum\CopyStatus;
use App\Enum\ReservationStatus;
use App\Enum\TransferStatus;
use App\Exception\Domain\ActiveReservationAlreadyExistsException;
use App\Exception\Domain\SubscriptionExpiredException;
use App\Repository\CopyRepository;
use App\Repository\PenaltyRepository;
use App\Repository\ReservationRepository;
use App\Repository\TransferRepository;
use App\Service\ReservationQueueService;
use App\Service\TransferService;
use App\Tests\Integration\BaseIntegrationTestCase;

class ReservationQueueServiceTest extends BaseIntegrationTestCase
{
    private ReservationQueueService $queueService;
    private TransferService $transferService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transferService = new TransferService($this->em, $this->clock);
        $reservationRepo = self::getContainer()->get(ReservationRepository::class);
        $copyRepo = self::getContainer()->get(CopyRepository::class);
        $penaltyRepo = self::getContainer()->get(PenaltyRepository::class);

        $this->queueService = new ReservationQueueService(
            $this->em,
            $this->clock,
            $reservationRepo,
            $copyRepo,
            $penaltyRepo,
            $this->transferService
        );
    }

    public function test_reservation_queue_follows_strict_fifo_order(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan();

        $member1 = $this->createMember($plan, $branch);
        $member2 = $this->createMember($plan, $branch);
        $member3 = $this->createMember($plan, $branch);

        $edition = $this->createEdition('Dune');
        // Copy is currently on loan (0 available copies)
        $copy = $this->createCopy($edition, $branch, CopyStatus::ON_LOAN);

        // Member 1 reserves at T0
        $res1 = $this->queueService->reserve($member1, $edition, $branch);
        $this->assertSame(ReservationStatus::WAITING, $res1->getStatus());

        // Advance clock by 1 hour -> Member 2 reserves at T0 + 1h
        $this->clock->modify('+1 hour');
        $res2 = $this->queueService->reserve($member2, $edition, $branch);
        $this->assertSame(ReservationStatus::WAITING, $res2->getStatus());

        // Advance clock by 1 hour -> Member 3 reserves at T0 + 2h
        $this->clock->modify('+1 hour');
        $res3 = $this->queueService->reserve($member3, $edition, $branch);
        $this->assertSame(ReservationStatus::WAITING, $res3->getStatus());

        // Copy is returned: first in line (Member 1) must be served!
        $this->clock->modify('+1 day');
        $allocatedRes = $this->queueService->handleCopyReturned($copy);

        $this->assertNotNull($allocatedRes);
        $this->assertSame($res1->getId(), $allocatedRes->getId());
        $this->assertSame(ReservationStatus::READY, $res1->getStatus());
        $this->assertSame($copy->getId(), $res1->getAssignedCopy()->getId());
        $this->assertSame(CopyStatus::HELD_FOR_PICKUP, $copy->getStatus());

        // Member 2 and Member 3 remain in WAITING state
        $this->em->refresh($res2);
        $this->em->refresh($res3);
        $this->assertSame(ReservationStatus::WAITING, $res2->getStatus());
        $this->assertSame(ReservationStatus::WAITING, $res3->getStatus());
    }

    public function test_return_triggers_waiting_reservation_and_sets_it_ready(): void
    {
        $branch = $this->createBranch('Médiathèque Centrale');
        $plan = $this->createPlan();
        $member = $this->createMember($plan, $branch);
        $edition = $this->createEdition('1984');
        $copy = $this->createCopy($edition, $branch, CopyStatus::ON_LOAN);

        $reservation = $this->queueService->reserve($member, $edition, $branch);
        $this->assertSame(ReservationStatus::WAITING, $reservation->getStatus());
        $this->assertNull($reservation->getPickupDeadlineAt());

        // Copy is returned at 2026-11-17 12:00:00
        $allocated = $this->queueService->handleCopyReturned($copy);

        $this->assertNotNull($allocated);
        $this->assertSame(ReservationStatus::READY, $reservation->getStatus());
        // 5-day pickup deadline: 2026-11-22 12:00:00
        $this->assertSame('2026-11-22 12:00:00', $reservation->getPickupDeadlineAt()->format('Y-m-d H:i:s'));
        $this->assertSame(CopyStatus::HELD_FOR_PICKUP, $copy->getStatus());
    }

    public function test_return_for_inter_branch_reservation_creates_transfer(): void
    {
        $branchCentral = $this->createBranch('Médiathèque Centrale', 'Site A');
        $branchSud = $this->createBranch('Médiathèque Sud', 'Site B');
        $plan = $this->createPlan();

        $member = $this->createMember($plan, $branchSud); // Member picks up at Site B
        $edition = $this->createEdition('Neuromancien');
        // Copy was loaned out from Site A
        $copy = $this->createCopy($edition, $branchCentral, CopyStatus::ON_LOAN);

        // Member reserves with pickup requested at branch Sud
        $reservation = $this->queueService->reserve($member, $edition, $branchSud);

        // Copy is returned at branch Central
        $this->queueService->handleCopyReturned($copy);

        // Since branch Central != branch Sud, an inter-site transfer must be initiated
        $this->assertSame(CopyStatus::IN_TRANSIT, $copy->getStatus());
        $this->assertSame(ReservationStatus::WAITING, $reservation->getStatus());

        $transfer = $this->em->getRepository(\App\Entity\Transfer::class)->findOneBy(['copy' => $copy]);
        $this->assertNotNull($transfer);
        $this->assertSame(TransferStatus::REQUESTED, $transfer->getStatus());
        $this->assertSame($branchCentral->getId(), $transfer->getSourceBranch()->getId());
        $this->assertSame($branchSud->getId(), $transfer->getTargetBranch()->getId());
        $this->assertSame($reservation->getId(), $transfer->getReservation()->getId());
    }

    public function test_expired_reservation_hands_copy_over_to_next_member_in_queue(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan();

        $member1 = $this->createMember($plan, $branch);
        $member2 = $this->createMember($plan, $branch);

        $edition = $this->createEdition('Fondation');
        $copy = $this->createCopy($edition, $branch, CopyStatus::AVAILABLE);

        // Member 1 reserves -> copy is immediately assigned and READY with 5-day deadline
        $res1 = $this->queueService->reserve($member1, $edition, $branch);
        $this->assertSame(ReservationStatus::READY, $res1->getStatus());
        $this->assertSame(CopyStatus::HELD_FOR_PICKUP, $copy->getStatus());

        // Member 2 reserves -> 0 copies left, placed in FIFO WAITING queue
        $this->clock->modify('+1 hour');
        $res2 = $this->queueService->reserve($member2, $edition, $branch);
        $this->assertSame(ReservationStatus::WAITING, $res2->getStatus());

        // Advance clock by 6 days (exceeding the 5-day deadline)
        $this->clock->modify('+6 days');

        // Command app:reservations:expire executes
        $expiredCount = $this->queueService->expireReservations();

        $this->assertSame(1, $expiredCount);
        $this->em->refresh($res1);
        $this->em->refresh($res2);
        $this->em->refresh($copy);

        // Member 1 reservation has expired and copy was removed
        $this->assertSame(ReservationStatus::EXPIRED, $res1->getStatus());
        $this->assertNull($res1->getAssignedCopy());

        // Copy was automatically reallocated to Member 2 (next in FIFO queue)
        $this->assertSame(ReservationStatus::READY, $res2->getStatus());
        $this->assertSame($copy->getId(), $res2->getAssignedCopy()->getId());
        $this->assertSame(CopyStatus::HELD_FOR_PICKUP, $copy->getStatus());
        // New 5-day deadline for Member 2 starting from expiration time
        $this->assertSame(
            $this->clock->now()->modify('+5 days')->format('Y-m-d H:i:s'),
            $res2->getPickupDeadlineAt()->format('Y-m-d H:i:s')
        );
    }

    public function test_reserve_refused_when_member_already_has_active_reservation_for_same_edition(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan();
        $member = $this->createMember($plan, $branch);

        $edition = $this->createEdition('Solaris');
        $this->createCopy($edition, $branch, CopyStatus::ON_LOAN);

        // First reservation succeeds
        $this->queueService->reserve($member, $edition, $branch);

        // Second reservation on the same edition must be rejected
        $this->expectException(ActiveReservationAlreadyExistsException::class);
        $this->expectExceptionMessageMatches('/already has an active reservation for edition/');

        $this->queueService->reserve($member, $edition, $branch);
    }

    public function test_reserve_refused_when_member_subscription_is_expired(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan();
        $expiredDate = $this->clock->now()->modify('-1 day');
        $member = $this->createMember($plan, $branch, $expiredDate);

        $edition = $this->createEdition();
        $this->createCopy($edition, $branch, CopyStatus::ON_LOAN);

        $this->expectException(SubscriptionExpiredException::class);

        $this->queueService->reserve($member, $edition, $branch);
    }
}
