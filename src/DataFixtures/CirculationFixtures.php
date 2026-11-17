<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Branch;
use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\Loan;
use App\Entity\Member;
use App\Entity\Penalty;
use App\Entity\Plan;
use App\Entity\Reservation;
use App\Entity\Transfer;
use App\Entity\User;
use App\Enum\CopyStatus;
use App\Enum\PenaltyReason;
use App\Enum\ReservationStatus;
use App\Enum\TransferStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\Clock\ClockInterface;

class CirculationFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly ClockInterface $clock
    ) {
    }

    public function getDependencies(): array
    {
        return [
            ReferenceFixtures::class,
            CatalogueFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $now = $this->clock->now();
        $faker = Factory::create('fr_FR');
        $faker->seed(20261117);

        /** @var Branch $branch1 */
        $branch1 = $this->getReference(ReferenceFixtures::BRANCH_1, Branch::class);
        /** @var Branch $branch2 */
        $branch2 = $this->getReference(ReferenceFixtures::BRANCH_2, Branch::class);
        $branches = [$branch1, $branch2];

        /** @var Plan $planYouth */
        $planYouth = $this->getReference(ReferenceFixtures::PLAN_YOUTH, Plan::class);
        /** @var Plan $planStandard */
        $planStandard = $this->getReference(ReferenceFixtures::PLAN_STANDARD, Plan::class);
        /** @var Plan $planPassion */
        $planPassion = $this->getReference(ReferenceFixtures::PLAN_PASSION, Plan::class);
        $plans = [$planYouth, $planStandard, $planPassion];

        /** @var Edition $popularEdition */
        $popularEdition = $this->getReference(CatalogueFixtures::EDITION_POPULAR, Edition::class);

        // -------------------------------------------------------------
        // 1. Create 50 Members
        // -------------------------------------------------------------
        /** @var list<Member> $members */
        $members = [];
        $activeLoanCounts = [];

        // Member 0: Primary Demo Member Account (ROLE_MEMBER)
        $demoUser = new User(
            'member@mediatheque.local',
            password_hash('Member123!', PASSWORD_BCRYPT),
            ['ROLE_MEMBER']
        );
        $manager->persist($demoUser);

        $demoMember = new Member(
            $demoUser,
            $planStandard,
            $branch1,
            'MBR-00001',
            $now->modify('+1 year'),
            $now->modify('-6 months')
        );
        $manager->persist($demoMember);
        $members[] = $demoMember;
        $activeLoanCounts[0] = 0;

        // Member 1: Expired Subscription Demo Member (ROLE_MEMBER)
        $expiredUser = new User(
            'expired.member@mediatheque.local',
            password_hash('Expired123!', PASSWORD_BCRYPT),
            ['ROLE_MEMBER']
        );
        $manager->persist($expiredUser);

        $expiredMember = new Member(
            $expiredUser,
            $planYouth,
            $branch2,
            'MBR-00002',
            $now->modify('-15 days'), // Expired 15 days ago
            $now->modify('-1 year')
        );
        $manager->persist($expiredMember);
        $members[] = $expiredMember;
        $activeLoanCounts[1] = 0;

        // Members 2 to 49: 48 active members with realistic names
        for ($i = 2; $i < 50; ++$i) {
            $userEmail = sprintf('member%d@example.fr', $i);
            $user = new User(
                $userEmail,
                password_hash('Password123!', PASSWORD_BCRYPT),
                ['ROLE_MEMBER']
            );
            $manager->persist($user);

            $memberPlan = $plans[$i % 3];
            $memberBranch = $branches[$i % 2];
            $membershipNumber = sprintf('MBR-%05d', $i + 1);

            $member = new Member(
                $user,
                $memberPlan,
                $memberBranch,
                $membershipNumber,
                $now->modify(sprintf('+%d days', $faker->numberBetween(30, 365))),
                $now->modify(sprintf('-%d days', $faker->numberBetween(30, 300)))
            );
            $manager->persist($member);
            $members[] = $member;
            $activeLoanCounts[$i] = 0;
        }

        // Flush members so all Member IDs are guaranteed generated and populated
        $manager->flush();

        // -------------------------------------------------------------
        // 2. Popular Edition Queue Scenario (Le Petit Prince)
        // Rule: Invariant: a WAITING reservation exists ONLY when no copy of the edition is available!
        // Le Petit Prince has copies copy-44 to copy-54 (11 copies).
        // We set ALL 11 copies to unavailable:
        // - 9 copies: ON_LOAN
        // - 1 copy: HELD_FOR_PICKUP (with a READY reservation)
        // - 1 copy: IN_TRANSIT (with an AWAITING_TRANSFER reservation and Transfer)
        // -------------------------------------------------------------
        $popularCopies = [];
        for ($c = 44; $c <= 54; ++$c) {
            /** @var Copy $copy */
            $copy = $this->getReference('copy-' . $c, Copy::class);
            $popularCopies[] = $copy;
        }

        // 9 copies ON_LOAN to members 2 to 10
        for ($k = 0; $k < 9; ++$k) {
            $popCopy = $popularCopies[$k];
            $popCopy->setStatus(CopyStatus::ON_LOAN);

            $borrower = $members[$k + 2];
            $loan = new Loan(
                $popCopy,
                $borrower,
                $now->modify(sprintf('-%d days', 10 + $k)),
                $now->modify(sprintf('+%d days', 15 - $k)),
                $popCopy->getId()
            );
            $manager->persist($loan);
            ++$activeLoanCounts[$k + 2];
        }

        // 1 copy HELD_FOR_PICKUP at Branch 1 for Member 11 (READY reservation)
        $heldPopCopy = $popularCopies[9];
        $heldPopCopy->setStatus(CopyStatus::HELD_FOR_PICKUP);
        $heldPopCopy->setCurrentBranch($branch1);

        $readyMember = $members[11];
        $readyReservation = new Reservation(
            $popularEdition,
            $readyMember,
            $branch1,
            ReservationStatus::READY,
            $now->modify('-6 days'),
            Reservation::computeActiveKey((int) $readyMember->getId(), (int) $popularEdition->getId())
        );
        $readyReservation->setAssignedCopy($heldPopCopy);
        $readyReservation->setPickupDeadlineAt($now->modify('+4 days')); // 5-day hold period
        $manager->persist($readyReservation);

        // 1 copy IN_TRANSIT from Branch 2 to Branch 1 for Member 12 (AWAITING_TRANSFER)
        $transitPopCopy = $popularCopies[10];
        $transitPopCopy->setStatus(CopyStatus::IN_TRANSIT);
        $transitPopCopy->setCurrentBranch($branch2); // remains source branch during transit

        $transitMember = $members[12];
        $awaitingReservation = new Reservation(
            $popularEdition,
            $transitMember,
            $branch1, // pickup branch
            ReservationStatus::AWAITING_TRANSFER,
            $now->modify('-2 days'),
            Reservation::computeActiveKey((int) $transitMember->getId(), (int) $popularEdition->getId())
        );
        $awaitingReservation->setAssignedCopy($transitPopCopy);
        $manager->persist($awaitingReservation);

        $transitTransfer = new Transfer(
            $transitPopCopy,
            $branch2,
            $branch1,
            TransferStatus::IN_TRANSIT,
            $now->modify('-1 day'),
            $awaitingReservation
        );
        $transitTransfer->setShippedAt($now->modify('-5 hours'));
        $manager->persist($transitTransfer);

        // Queue of 3 WAITING members on Le Petit Prince (Members 13, 14, 15)
        // Since all 11 copies are unavailable, this queue is 100% compliant with the invariant!
        $waitingMembers = [$members[13], $members[14], $members[15]];
        foreach ($waitingMembers as $wIndex => $wMember) {
            $waitingReservation = new Reservation(
                $popularEdition,
                $wMember,
                $branch1,
                ReservationStatus::WAITING,
                $now->modify(sprintf('-%d days', 3 - $wIndex)),
                Reservation::computeActiveKey((int) $wMember->getId(), (int) $popularEdition->getId())
            );
            $manager->persist($waitingReservation);
        }

        // -------------------------------------------------------------
        // 3. Demo Member Scenario (Member 0):
        // 1 ongoing loan on time + 1 OVERDUE loan + 1 returned loan
        // -------------------------------------------------------------
        /** @var Copy $demoOngoingCopy */
        $demoOngoingCopy = $this->getReference('copy-1', Copy::class);
        $demoOngoingCopy->setStatus(CopyStatus::ON_LOAN);
        $demoLoan = new Loan(
            $demoOngoingCopy,
            $demoMember,
            $now->modify('-10 days'),
            $now->modify('+18 days'),
            $demoOngoingCopy->getId()
        );
        $manager->persist($demoLoan);
        ++$activeLoanCounts[0];

        // Overdue Loan Demo Scenario
        /** @var Copy $demoOverdueCopy */
        $demoOverdueCopy = $this->getReference('copy-2', Copy::class);
        $demoOverdueCopy->setStatus(CopyStatus::ON_LOAN);
        $demoOverdueLoan = new Loan(
            $demoOverdueCopy,
            $demoMember,
            $now->modify('-40 days'),
            $now->modify('-12 days'), // 12 days overdue!
            $demoOverdueCopy->getId()
        );
        $manager->persist($demoOverdueLoan);
        ++$activeLoanCounts[0];

        // -------------------------------------------------------------
        // 4. Additional Active Ongoing Loans across copies 3 to 30
        // (Ensuring no member exceeds plan quota)
        // -------------------------------------------------------------
        for ($c = 3; $c <= 25; ++$c) {
            /** @var Copy $copy */
            $copy = $this->getReference('copy-' . $c, Copy::class);
            $copy->setStatus(CopyStatus::ON_LOAN);

            // Find a member with room in their quota (excluding expired member 1)
            $memberIdx = 16 + ($c % 30);
            $borrower = $members[$memberIdx];
            if ($activeLoanCounts[$memberIdx] < $borrower->getPlan()->getMaxSimultaneousLoans()) {
                $loan = new Loan(
                    $copy,
                    $borrower,
                    $now->modify(sprintf('-%d days', $faker->numberBetween(3, 14))),
                    $now->modify(sprintf('+%d days', $faker->numberBetween(7, 21))),
                    $copy->getId()
                );
                $manager->persist($loan);
                ++$activeLoanCounts[$memberIdx];
            }
        }

        // -------------------------------------------------------------
        // 5. Past Returned Loans & Penalties
        // Copies 60 to 95 (status remains AVAILABLE, activeCopyId is NULL)
        // -------------------------------------------------------------
        for ($c = 60; $c <= 90; ++$c) {
            /** @var Copy $copy */
            $copy = $this->getReference('copy-' . $c, Copy::class);
            // Copy is AVAILABLE
            $memberIdx = 5 + ($c % 40);
            $borrower = $members[$memberIdx];

            $borrowedAt = $now->modify(sprintf('-%d days', $faker->numberBetween(45, 90)));
            $dueDate = $borrowedAt->modify('+28 days');
            $returnedAt = $borrowedAt->modify(sprintf('+%d days', $faker->numberBetween(10, 27))); // On time

            $pastLoan = new Loan(
                $copy,
                $borrower,
                $borrowedAt,
                $dueDate,
                null // activeCopyId is NULL for returned loans
            );
            $pastLoan->setReturnedAt($returnedAt);
            $manager->persist($pastLoan);
        }

        // Late returned loan scenario with UNPAID penalty (Member 3)
        /** @var Copy $lateCopyUnpaid */
        $lateCopyUnpaid = $this->getReference('copy-91', Copy::class);
        $lateBorrowedAt1 = $now->modify('-50 days');
        $lateDueDate1 = $lateBorrowedAt1->modify('+28 days'); // Due 22 days ago
        $lateReturnedAt1 = $now->modify('-15 days'); // Returned 7 days late

        $lateLoanUnpaid = new Loan($lateCopyUnpaid, $members[3], $lateBorrowedAt1, $lateDueDate1, null);
        $lateLoanUnpaid->setReturnedAt($lateReturnedAt1);
        $manager->persist($lateLoanUnpaid);

        $unpaidPenalty = new Penalty(
            $lateLoanUnpaid,
            '14.00', // 7 days * 2.00 MAD
            PenaltyReason::LATE,
            $lateReturnedAt1,
            false // Unpaid
        );
        $manager->persist($unpaidPenalty);

        // Late returned loan scenario with PAID penalty (Member 4)
        /** @var Copy $lateCopyPaid */
        $lateCopyPaid = $this->getReference('copy-92', Copy::class);
        $lateBorrowedAt2 = $now->modify('-60 days');
        $lateDueDate2 = $lateBorrowedAt2->modify('+28 days'); // Due 32 days ago
        $lateReturnedAt2 = $now->modify('-27 days'); // Returned 5 days late

        $lateLoanPaid = new Loan($lateCopyPaid, $members[4], $lateBorrowedAt2, $lateDueDate2, null);
        $lateLoanPaid->setReturnedAt($lateReturnedAt2);
        $manager->persist($lateLoanPaid);

        $paidPenalty = new Penalty(
            $lateLoanPaid,
            '10.00', // 5 days * 2.00 MAD
            PenaltyReason::LATE,
            $lateReturnedAt2,
            true, // Paid
            $now->modify('-20 days')
        );
        $manager->persist($paidPenalty);

        // -------------------------------------------------------------
        // 6. Lost & Damaged Copies Demo
        // -------------------------------------------------------------
        /** @var Copy $lostCopy */
        $lostCopy = $this->getReference('copy-290', Copy::class);
        $lostCopy->setStatus(CopyStatus::LOST);

        /** @var Copy $damagedCopy */
        $damagedCopy = $this->getReference('copy-291', Copy::class);
        $damagedCopy->setStatus(CopyStatus::DAMAGED);

        // -------------------------------------------------------------
        // 7. Completed Past Transfer Demo
        // -------------------------------------------------------------
        /** @var Copy $completedTransferCopy */
        $completedTransferCopy = $this->getReference('copy-100', Copy::class);
        $completedTransfer = new Transfer(
            $completedTransferCopy,
            $branch1,
            $branch2,
            TransferStatus::RECEIVED,
            $now->modify('-10 days')
        );
        $completedTransfer->setShippedAt($now->modify('-9 days'));
        $completedTransfer->setReceivedAt($now->modify('-8 days'));
        $manager->persist($completedTransfer);

        $manager->flush();
    }
}
