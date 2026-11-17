<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

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
use App\Service\StatisticsService;
use App\Tests\Integration\BaseIntegrationTestCase;

class StatisticsServiceTest extends BaseIntegrationTestCase
{
    private StatisticsService $statisticsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statisticsService = new StatisticsService($this->em);
    }

    public function testTurnoverStatsOnEmptyDatabase(): void
    {
        $stats = $this->statisticsService->getTurnoverStats();

        self::assertSame(0, $stats['totalCopies']);
        self::assertSame(0, $stats['totalLoans']);
        self::assertSame(0.0, $stats['globalTurnoverRate']);
        self::assertCount(3, $stats['byMediaType']);
    }

    public function testTurnoverAndRankingStatsWithData(): void
    {
        $centrale = new Branch('Médiathèque Centrale', '10 Rue de Paris', 'Paris');
        $annexe = new Branch('Médiathèque Annexe', '5 Avenue Victor Hugo', 'Paris');
        $plan = new Plan('Standard', 5, 21, 2, 14, '1.00');

        $user1 = new User('member1@mediatheque.local', 'hash', ['ROLE_MEMBER']);
        $user2 = new User('member2@mediatheque.local', 'hash', ['ROLE_MEMBER']);

        $member1 = new Member($user1, $plan, $centrale, 'CARD-S1', new \DateTimeImmutable('+1 year'), new \DateTimeImmutable('now'));
        $member2 = new Member($user2, $plan, $annexe, 'CARD-S2', new \DateTimeImmutable('+1 year'), new \DateTimeImmutable('now'));

        $this->em->persist($centrale);
        $this->em->persist($annexe);
        $this->em->persist($plan);
        $this->em->persist($user1);
        $this->em->persist($user2);
        $this->em->persist($member1);
        $this->em->persist($member2);

        // Edition A: Book, 2 copies at Centrale
        $workA = new Work('Livre Populaire');
        $editionA = new Edition($workA, 'Livre Populaire', 'Gallimard', 2020, MediaType::BOOK, CompletenessStatus::COMPLETE, new \DateTimeImmutable('2020-01-01'));
        $copyA1 = new Copy($editionA, $centrale, 'BC-A1', CopyStatus::AVAILABLE, new \DateTimeImmutable('now'));
        $copyA2 = new Copy($editionA, $centrale, 'BC-A2', CopyStatus::AVAILABLE, new \DateTimeImmutable('now'));

        // Edition B: Comic, 1 copy at Annexe
        $workB = new Work('BD Culte');
        $editionB = new Edition($workB, 'BD Culte', 'Dargaud', 2021, MediaType::COMIC, CompletenessStatus::COMPLETE, new \DateTimeImmutable('2021-01-01'));
        $copyB1 = new Copy($editionB, $annexe, 'BC-B1', CopyStatus::AVAILABLE, new \DateTimeImmutable('now'));

        // Edition C: DVD, 1 copy at Annexe (NEVER BORROWED)
        $workC = new Work('Film Classique');
        $editionC = new Edition($workC, 'Film Classique', 'Warner', 2019, MediaType::DVD, CompletenessStatus::COMPLETE, new \DateTimeImmutable('2019-01-01'));
        $copyC1 = new Copy($editionC, $annexe, 'BC-C1', CopyStatus::AVAILABLE, new \DateTimeImmutable('now'));

        $this->em->persist($workA);
        $this->em->persist($editionA);
        $this->em->persist($copyA1);
        $this->em->persist($copyA2);

        $this->em->persist($workB);
        $this->em->persist($editionB);
        $this->em->persist($copyB1);

        $this->em->persist($workC);
        $this->em->persist($editionC);
        $this->em->persist($copyC1);

        // Loans:
        // Copy A1 borrowed twice (past + active)
        $loanA1Past = new Loan($copyA1, $member1, new \DateTimeImmutable('-30 days'), new \DateTimeImmutable('-9 days'));
        $loanA1Past->markReturned(new \DateTimeImmutable('-10 days'));

        $loanA1Active = new Loan($copyA1, $member2, new \DateTimeImmutable('-5 days'), new \DateTimeImmutable('+16 days'));

        // Copy A2 borrowed once (past)
        $loanA2Past = new Loan($copyA2, $member1, new \DateTimeImmutable('-20 days'), new \DateTimeImmutable('+1 day'));
        $loanA2Past->markReturned(new \DateTimeImmutable('-1 day'));

        // Copy B1 borrowed once (active)
        $loanB1Active = new Loan($copyB1, $member1, new \DateTimeImmutable('-2 days'), new \DateTimeImmutable('+19 days'));

        $this->em->persist($loanA1Past);
        $this->em->persist($loanA1Active);
        $this->em->persist($loanA2Past);
        $this->em->persist($loanB1Active);

        $this->em->flush();

        // 1. Turnover stats
        // Total copies: 4, Total loans: 4 -> Global turnover: 1.00
        $turnover = $this->statisticsService->getTurnoverStats();
        self::assertSame(4, $turnover['totalCopies']);
        self::assertSame(4, $turnover['totalLoans']);
        self::assertSame(1.0, $turnover['globalTurnoverRate']);

        // Check Media types: Book: 2 copies, 3 loans -> 1.50
        $bookStat = null;
        foreach ($turnover['byMediaType'] as $stat) {
            if ($stat['type'] === MediaType::BOOK) {
                $bookStat = $stat;
                break;
            }
        }
        self::assertNotNull($bookStat);
        self::assertSame(2, $bookStat['copies']);
        self::assertSame(3, $bookStat['loans']);
        self::assertSame(1.5, $bookStat['rate']);

        // Check DVD: 1 copy, 0 loans -> 0.00
        $dvdStat = null;
        foreach ($turnover['byMediaType'] as $stat) {
            if ($stat['type'] === MediaType::DVD) {
                $dvdStat = $stat;
                break;
            }
        }
        self::assertNotNull($dvdStat);
        self::assertSame(1, $dvdStat['copies']);
        self::assertSame(0, $dvdStat['loans']);
        self::assertSame(0.0, $dvdStat['rate']);

        // 2. Most Borrowed: Edition A (3 loans), Edition B (1 loan)
        $mostBorrowed = $this->statisticsService->getMostBorrowedEditions(5);
        self::assertCount(2, $mostBorrowed);
        self::assertSame($editionA->getId(), $mostBorrowed[0]['edition']->getId());
        self::assertSame(3, $mostBorrowed[0]['loanCount']);
        self::assertSame($editionB->getId(), $mostBorrowed[1]['edition']->getId());
        self::assertSame(1, $mostBorrowed[1]['loanCount']);

        // 3. Never Borrowed: Edition C
        $neverBorrowed = $this->statisticsService->getNeverBorrowedEditions(5);
        self::assertCount(1, $neverBorrowed);
        self::assertSame($editionC->getId(), $neverBorrowed[0]->getId());
        self::assertSame(1, $this->statisticsService->countNeverBorrowedEditions());

        // 4. Member Activity: 2 members, both have an active loan -> 100%
        $memberActivity = $this->statisticsService->getMemberActivityStats();
        self::assertSame(2, $memberActivity['totalMembers']);
        self::assertSame(2, $memberActivity['activeBorrowersCount']);
        self::assertSame(100.0, $memberActivity['activeRate']);
    }
}
