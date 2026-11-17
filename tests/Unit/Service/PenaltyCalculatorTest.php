<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Branch;
use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\Loan;
use App\Entity\Member;
use App\Entity\Plan;
use App\Entity\User;
use App\Entity\Work;
use App\Enum\CompletenessStatus;
use App\Enum\CopyStatus;
use App\Enum\MediaType;
use App\Service\PenaltyCalculator;
use PHPUnit\Framework\TestCase;

class PenaltyCalculatorTest extends TestCase
{
    private PenaltyCalculator $calculator;
    private Plan $plan;
    private Member $member;
    private Copy $copy;

    protected function setUp(): void
    {
        $this->calculator = new PenaltyCalculator();

        $branch = new Branch('Centrale', '10 Avenue Hassan II');
        $this->plan = new Plan('Standard', 3, 21, 1, 14, '2.00');
        $user = new User('member@test.local', 'password');
        $this->member = new Member($user, $this->plan, $branch, 'ADH-0001', new \DateTimeImmutable('+1 year'), new \DateTimeImmutable('now'));

        $work = new Work('Le Petit Prince');
        $edition = new Edition($work, 'Le Petit Prince', 'Gallimard', 1943, MediaType::BOOK, CompletenessStatus::COMPLETE, new \DateTimeImmutable('now'), '9782070612758');
        $this->copy = new Copy($edition, $branch, 'EX-0001', CopyStatus::AVAILABLE, new \DateTimeImmutable('now'));
    }

    public function test_return_on_time_generates_no_penalty(): void
    {
        $borrowedAt = new \DateTimeImmutable('2026-11-01 10:00:00');
        $dueDate = new \DateTimeImmutable('2026-11-22 00:00:00');
        $loan = new Loan($this->copy, $this->member, $borrowedAt, $dueDate);

        // Returned on the due date at 15:00
        $returnedAt = new \DateTimeImmutable('2026-11-22 15:00:00');

        $this->assertSame(0, $this->calculator->calculateLateDays($loan, $returnedAt));
        $this->assertNull($this->calculator->calculateLatePenalty($loan, $returnedAt));
    }

    public function test_return_before_due_date_generates_no_penalty(): void
    {
        $borrowedAt = new \DateTimeImmutable('2026-11-01 10:00:00');
        $dueDate = new \DateTimeImmutable('2026-11-22 00:00:00');
        $loan = new Loan($this->copy, $this->member, $borrowedAt, $dueDate);

        $returnedAt = new \DateTimeImmutable('2026-11-15 11:30:00');

        $this->assertSame(0, $this->calculator->calculateLateDays($loan, $returnedAt));
        $this->assertNull($this->calculator->calculateLatePenalty($loan, $returnedAt));
    }

    public function test_return_late_generates_correct_penalty_amount_based_on_plan_daily_rate(): void
    {
        $borrowedAt = new \DateTimeImmutable('2026-11-01 10:00:00');
        $dueDate = new \DateTimeImmutable('2026-11-22 00:00:00');
        $loan = new Loan($this->copy, $this->member, $borrowedAt, $dueDate);

        // Returned 3 days late (due end of day was 2026-11-22 23:59:59, returned 2026-11-25 14:00:00)
        $returnedAt = new \DateTimeImmutable('2026-11-25 14:00:00');

        $this->assertSame(3, $this->calculator->calculateLateDays($loan, $returnedAt));
        // Plan rate is 2.00 MAD/day -> 3 * 2.00 = 6.00 MAD
        $this->assertSame('6.00', $this->calculator->calculateLatePenalty($loan, $returnedAt));
    }

    public function test_fractional_daily_rate_calculation(): void
    {
        $this->plan->setPenaltyRatePerDay('3.50');
        $borrowedAt = new \DateTimeImmutable('2026-11-01 10:00:00');
        $dueDate = new \DateTimeImmutable('2026-11-22 00:00:00');
        $loan = new Loan($this->copy, $this->member, $borrowedAt, $dueDate);

        // 4 days late
        $returnedAt = new \DateTimeImmutable('2026-11-26 12:00:00');

        $this->assertSame(4, $this->calculator->calculateLateDays($loan, $returnedAt));
        // 4 * 3.50 = 14.00 MAD
        $this->assertSame('14.00', $this->calculator->calculateLatePenalty($loan, $returnedAt));
    }
}
