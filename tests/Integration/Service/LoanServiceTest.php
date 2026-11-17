<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\Loan;
use App\Entity\Penalty;
use App\Enum\CopyStatus;
use App\Enum\PenaltyReason;
use App\Exception\Domain\CopyNotAvailableException;
use App\Exception\Domain\LoanMaxExtensionsReachedException;
use App\Exception\Domain\LoanOverdueException;
use App\Exception\Domain\LoanQuotaExceededException;
use App\Exception\Domain\ReservationQueueNotEmptyException;
use App\Exception\Domain\SubscriptionExpiredException;
use App\Exception\Domain\UnpaidPenaltyException;
use App\Repository\LoanRepository;
use App\Repository\PenaltyRepository;
use App\Repository\ReservationRepository;
use App\Service\LoanService;
use App\Service\PenaltyCalculator;
use App\Service\ReservationQueueService;
use App\Tests\Integration\BaseIntegrationTestCase;

class LoanServiceTest extends BaseIntegrationTestCase
{
    private LoanService $loanService;
    private ReservationQueueService $reservationQueueService;

    protected function setUp(): void
    {
        parent::setUp();

        $penaltyCalculator = new PenaltyCalculator();
        $this->reservationQueueService = self::getContainer()->get(ReservationQueueService::class);
        $loanRepo = self::getContainer()->get(LoanRepository::class);
        $reservationRepo = self::getContainer()->get(ReservationRepository::class);
        $penaltyRepo = self::getContainer()->get(PenaltyRepository::class);

        $this->loanService = new LoanService(
            $this->em,
            $this->clock,
            $penaltyCalculator,
            $this->reservationQueueService,
            $loanRepo,
            $reservationRepo,
            $penaltyRepo
        );
    }

    public function test_borrow_sets_due_date_according_to_plan_duration(): void
    {
        $branch = $this->createBranch();
        // 21 days loan duration
        $plan = $this->createPlan('Standard', 3, 21, 1, 14, '2.00');
        $member = $this->createMember($plan, $branch);
        $edition = $this->createEdition();
        $copy = $this->createCopy($edition, $branch);

        $loan = $this->loanService->borrow($member, $copy);

        $this->assertSame(CopyStatus::ON_LOAN, $copy->getStatus());
        $this->assertSame($copy->getId(), $loan->getActiveCopyId());
        $this->assertSame('2026-11-17 12:00:00', $loan->getBorrowedAt()->format('Y-m-d H:i:s'));
        // 2026-11-17 + 21 days = 2026-12-08
        $this->assertSame('2026-12-08', $loan->getDueDate()->format('Y-m-d'));
        $this->assertNull($loan->getReturnedAt());
        $this->assertSame(0, $loan->getExtensionCount());
    }

    public function test_borrow_refused_when_simultaneous_loan_quota_reached(): void
    {
        $branch = $this->createBranch();
        // Quota: exactly 2 simultaneous loans
        $plan = $this->createPlan('Limited', 2, 14, 0, 0, '1.50');
        $member = $this->createMember($plan, $branch);

        $edition1 = $this->createEdition('Titre 1');
        $copy1 = $this->createCopy($edition1, $branch);
        $edition2 = $this->createEdition('Titre 2');
        $copy2 = $this->createCopy($edition2, $branch);
        $edition3 = $this->createEdition('Titre 3');
        $copy3 = $this->createCopy($edition3, $branch);

        $this->loanService->borrow($member, $copy1);
        $this->loanService->borrow($member, $copy2);

        $this->expectException(LoanQuotaExceededException::class);
        $this->expectExceptionMessage('Loan quota exceeded: plan allows a maximum of 2 simultaneous loans, member currently has 2.');

        $this->loanService->borrow($member, $copy3);
    }

    public function test_borrow_refused_when_member_subscription_is_expired(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan();
        // Subscription ended yesterday
        $expiredDate = $this->clock->now()->modify('-1 day');
        $member = $this->createMember($plan, $branch, $expiredDate);

        $edition = $this->createEdition();
        $copy = $this->createCopy($edition, $branch);

        $this->expectException(SubscriptionExpiredException::class);
        $this->expectExceptionMessageMatches('/Cannot borrow: subscription for member ADH-\d+ expired on/');

        $this->loanService->borrow($member, $copy);
    }

    public function test_borrow_refused_when_member_has_unpaid_penalty(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan();
        $member = $this->createMember($plan, $branch);

        $editionOld = $this->createEdition('Livre Ancien');
        $copyOld = $this->createCopy($editionOld, $branch);

        // Previous loan with unpaid penalty
        $oldLoan = new Loan($copyOld, $member, $this->clock->now()->modify('-30 days'), $this->clock->now()->modify('-10 days'));
        $oldLoan->markReturned($this->clock->now()->modify('-5 days'));
        $this->em->persist($oldLoan);

        $penalty = new Penalty($oldLoan, '15.00', PenaltyReason::LATE, $this->clock->now()->modify('-5 days'), false);
        $this->em->persist($penalty);
        $this->em->flush();

        $newEdition = $this->createEdition('Livre Nouveau');
        $newCopy = $this->createCopy($newEdition, $branch);

        $this->expectException(UnpaidPenaltyException::class);
        $this->expectExceptionMessage('unpaid penalties totaling 15.00 MAD');

        $this->loanService->borrow($member, $newCopy);
    }

    public function test_borrow_refused_when_copy_is_not_available(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan();
        $member = $this->createMember($plan, $branch);

        $edition = $this->createEdition();
        // Copy is already on loan or in transit
        $copy = $this->createCopy($edition, $branch, CopyStatus::ON_LOAN);

        $this->expectException(CopyNotAvailableException::class);
        $this->expectExceptionMessageMatches('/is not available for loan \(current status: ON_LOAN\)/i');

        $this->loanService->borrow($member, $copy);
    }

    public function test_return_on_time_generates_no_penalty(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan('Standard', 3, 21, 1, 14, '2.00');
        $member = $this->createMember($plan, $branch);
        $edition = $this->createEdition();
        $copy = $this->createCopy($edition, $branch);

        $loan = $this->loanService->borrow($member, $copy);

        // Advance clock by 5 days (well before 21 days due date)
        $this->clock->modify('+5 days');

        $penalty = $this->loanService->return($loan);

        $this->assertNull($penalty);
        $this->assertSame(CopyStatus::AVAILABLE, $copy->getStatus());
        $this->assertNull($loan->getActiveCopyId());
        $this->assertNotNull($loan->getReturnedAt());
    }

    public function test_return_late_generates_correct_penalty_amount_based_on_plan_daily_rate(): void
    {
        $branch = $this->createBranch();
        // Plan daily rate: 2.50 MAD
        $plan = $this->createPlan('Standard', 3, 10, 1, 14, '2.50');
        $member = $this->createMember($plan, $branch);
        $edition = $this->createEdition();
        $copy = $this->createCopy($edition, $branch);

        $loan = $this->loanService->borrow($member, $copy);

        // Due date was in 10 days (2026-11-27). Advance clock to 14 days later (2026-12-01) -> 4 days late
        $this->clock->modify('+14 days');

        $penalty = $this->loanService->return($loan);

        $this->assertNotNull($penalty);
        $this->assertSame('10.00', $penalty->getAmount()); // 4 * 2.50 = 10.00 MAD
        $this->assertSame(PenaltyReason::LATE, $penalty->getReason());
        $this->assertFalse($penalty->isPaid());
        $this->assertSame(CopyStatus::AVAILABLE, $copy->getStatus());
    }

    public function test_extend_accepted_when_no_reservation_waiting(): void
    {
        $branch = $this->createBranch();
        // 21 days loan + 14 days extension
        $plan = $this->createPlan('Standard', 3, 21, 1, 14, '2.00');
        $member = $this->createMember($plan, $branch);
        $edition = $this->createEdition();
        $copy = $this->createCopy($edition, $branch);

        $loan = $this->loanService->borrow($member, $copy);
        $initialDueDate = $loan->getDueDate();

        // Advance 10 days (still within original 21 days period)
        $this->clock->modify('+10 days');

        $extendedLoan = $this->loanService->extend($loan);

        $this->assertSame(1, $extendedLoan->getExtensionCount());
        // Initial due date + 14 days
        $this->assertSame(
            $initialDueDate->modify('+14 days')->format('Y-m-d'),
            $extendedLoan->getDueDate()->format('Y-m-d')
        );
    }

    public function test_extend_refused_when_loan_is_already_overdue(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan('Standard', 3, 10, 1, 14, '2.00');
        $member = $this->createMember($plan, $branch);
        $edition = $this->createEdition();
        $copy = $this->createCopy($edition, $branch);

        $loan = $this->loanService->borrow($member, $copy);

        // Advance 12 days (past 10-day due date)
        $this->clock->modify('+12 days');

        $this->expectException(LoanOverdueException::class);
        $this->expectExceptionMessageMatches('/is already overdue since/');

        $this->loanService->extend($loan);
    }

    public function test_extend_refused_when_max_extensions_quota_reached(): void
    {
        $branch = $this->createBranch();
        // Plan allows max 1 extension
        $plan = $this->createPlan('Standard', 3, 21, 1, 14, '2.00');
        $member = $this->createMember($plan, $branch);
        $edition = $this->createEdition();
        $copy = $this->createCopy($edition, $branch);

        $loan = $this->loanService->borrow($member, $copy);

        // First extension succeeds
        $this->loanService->extend($loan);
        $this->assertSame(1, $loan->getExtensionCount());

        // Second extension attempt must be refused
        $this->expectException(LoanMaxExtensionsReachedException::class);
        $this->expectExceptionMessage('maximum extension limit of 1 has already been reached');

        $this->loanService->extend($loan);
    }

    public function test_extend_refused_when_reservation_is_waiting_on_edition(): void
    {
        $branch = $this->createBranch();
        $plan = $this->createPlan('Standard', 3, 21, 1, 14, '2.00');
        $borrower = $this->createMember($plan, $branch);
        $waitingMember = $this->createMember($plan, $branch);

        $edition = $this->createEdition();
        $copy = $this->createCopy($edition, $branch);

        $loan = $this->loanService->borrow($borrower, $copy);

        // Another member places a reservation while the copy is out on loan
        $this->reservationQueueService->reserve($waitingMember, $edition, $branch);

        $this->expectException(ReservationQueueNotEmptyException::class);
        $this->expectExceptionMessageMatches('/1 reservation\(s\) are currently waiting for edition/');

        $this->loanService->extend($loan);
    }
}
