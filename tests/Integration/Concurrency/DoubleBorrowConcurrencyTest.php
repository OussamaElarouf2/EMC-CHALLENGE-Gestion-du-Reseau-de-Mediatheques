<?php

declare(strict_types=1);

namespace App\Tests\Integration\Concurrency;

use App\Entity\Loan;
use App\Entity\Reservation;
use App\Enum\CopyStatus;
use App\Enum\ReservationStatus;
use App\Tests\Integration\BaseIntegrationTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class DoubleBorrowConcurrencyTest extends BaseIntegrationTestCase
{
    public function test_concurrent_double_loan_is_prevented_by_active_copy_id_unique_index(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan();

        $member1 = $this->createMember($plan, $branch);
        $member2 = $this->createMember($plan, $branch);

        $edition = $this->createEdition('Fondation et Empire');
        $copy = $this->createCopy($edition, $branch, CopyStatus::AVAILABLE);

        // Transaction 1: Member 1 creates active loan
        $loan1 = new Loan($copy, $member1, $this->clock->now(), $this->clock->now()->modify('+21 days'));
        $this->em->persist($loan1);
        $this->em->flush();

        $this->assertSame($copy->getId(), $loan1->getActiveCopyId());

        // Concurrent Transaction 2: Member 2 tries to borrow the same copy simultaneously
        $loan2 = new Loan($copy, $member2, $this->clock->now(), $this->clock->now()->modify('+21 days'));
        $this->em->persist($loan2);

        // The database level unique constraint UNIQ_LOAN_ACTIVE_COPY must strictly block this!
        $this->expectException(UniqueConstraintViolationException::class);

        $this->em->flush();
    }

    public function test_returned_loans_do_not_conflict_because_active_copy_id_is_null(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan();

        $member1 = $this->createMember($plan, $branch);
        $member2 = $this->createMember($plan, $branch);

        $edition = $this->createEdition('Seconde Fondation');
        $copy = $this->createCopy($edition, $branch, CopyStatus::AVAILABLE);

        // Member 1 borrows and returns the copy
        $loan1 = new Loan($copy, $member1, $this->clock->now()->modify('-30 days'), $this->clock->now()->modify('-10 days'));
        $loan1->markReturned($this->clock->now()->modify('-15 days'));
        $this->em->persist($loan1);
        $this->em->flush();

        $this->assertNull($loan1->getActiveCopyId());

        // Member 2 borrows the same copy later
        $loan2 = new Loan($copy, $member2, $this->clock->now()->modify('-10 days'), $this->clock->now()->modify('+11 days'));
        $loan2->markReturned($this->clock->now()->modify('-2 days'));
        $this->em->persist($loan2);
        $this->em->flush();

        $this->assertNull($loan2->getActiveCopyId());

        // Member 3 borrows the same copy currently
        $member3 = $this->createMember($plan, $branch);
        $loan3 = new Loan($copy, $member3, $this->clock->now(), $this->clock->now()->modify('+21 days'));
        $this->em->persist($loan3);
        $this->em->flush();

        $this->assertSame($copy->getId(), $loan3->getActiveCopyId());

        // All 3 loans coexist cleanly in history: 2 past loans (activeCopyId=NULL) and 1 current loan (activeCopyId=copyId)
        $historicalLoans = $this->em->getRepository(Loan::class)->findBy(['copy' => $copy]);
        $this->assertCount(3, $historicalLoans);
    }

    public function test_active_reservation_unique_key_blocks_duplicate_concurrent_reservations(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan();
        $member = $this->createMember($plan, $branch);

        $edition = $this->createEdition('Dune');

        // Reservation 1
        $res1 = new Reservation($edition, $member, $branch, ReservationStatus::WAITING, $this->clock->now());
        $this->em->persist($res1);
        $this->em->flush();

        $this->assertNotNull($res1->getActiveMemberEditionKey());

        // Concurrent duplicate reservation attempt by same member on same edition
        $res2 = new Reservation($edition, $member, $branch, ReservationStatus::WAITING, $this->clock->now());
        $this->em->persist($res2);

        // Database unique constraint UNIQ_RES_ACTIVE_MEMBER_EDITION must reject duplicate
        $this->expectException(UniqueConstraintViolationException::class);

        $this->em->flush();
    }
}
