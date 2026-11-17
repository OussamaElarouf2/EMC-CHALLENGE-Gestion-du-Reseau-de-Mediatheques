<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Branch;
use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\Member;
use App\Entity\Plan;
use App\Entity\User;
use App\Entity\Work;
use App\Enum\CompletenessStatus;
use App\Enum\CopyStatus;
use App\Enum\MediaType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

abstract class BaseIntegrationTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;
    protected MockClock $clock;
    private static int $memberSeq = 1;
    private static int $copySeq = 1;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->clock = new MockClock('2026-11-17 12:00:00');

        $this->purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    protected function purgeDatabase(): void
    {
        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $tables = [
            'penalty',
            'transfer',
            'reservation',
            'loan',
            'copy',
            'edition_contributor',
            'edition',
            'contributor',
            'work',
            'member',
            'app_user',
            'plan',
            'branch',
        ];

        foreach ($tables as $table) {
            $conn->executeStatement(sprintf('TRUNCATE TABLE `%s`', $table));
        }

        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function createBranch(string $name = 'Médiathèque Centrale', string $address = '10 Avenue Hassan II'): Branch
    {
        $branch = new Branch($name, $address);
        $this->em->persist($branch);
        $this->em->flush();

        return $branch;
    }

    protected function createPlan(
        string $name = 'Formule Standard',
        int $maxLoans = 3,
        int $loanDurationDays = 21,
        int $maxExtensions = 1,
        int $extensionDays = 14,
        string $penaltyRate = '2.00'
    ): Plan {
        $plan = new Plan($name, $maxLoans, $loanDurationDays, $maxExtensions, $extensionDays, $penaltyRate);
        $this->em->persist($plan);
        $this->em->flush();

        return $plan;
    }

    protected function createMember(
        Plan $plan,
        Branch $branch,
        ?\DateTimeImmutable $subscriptionEndsAt = null,
        ?string $email = null
    ): Member {
        $seq = self::$memberSeq++;
        $userEmail = $email ?? sprintf('user_%d@mediatheque.local', $seq);
        $user = new User($userEmail, 'password_hash', ['ROLE_MEMBER']);
        $this->em->persist($user);

        $endsAt = $subscriptionEndsAt ?? $this->clock->now()->modify('+1 year');
        $number = sprintf('ADH-%04d', $seq);
        $member = new Member($user, $plan, $branch, $number, $endsAt, $this->clock->now());

        $this->em->persist($member);
        $this->em->flush();

        return $member;
    }

    protected function createEdition(string $title = 'Le Petit Prince', ?string $isbn = null): Edition
    {
        $work = new Work($title);
        $this->em->persist($work);

        $edition = new Edition(
            $work,
            $title,
            'Gallimard',
            1943,
            MediaType::BOOK,
            CompletenessStatus::COMPLETE,
            $this->clock->now(),
            $isbn ?? sprintf('978207%07d', self::$copySeq)
        );
        $this->em->persist($edition);
        $this->em->flush();

        return $edition;
    }

    protected function createCopy(
        Edition $edition,
        Branch $branch,
        CopyStatus $status = CopyStatus::AVAILABLE,
        ?string $barcode = null
    ): Copy {
        $seq = self::$copySeq++;
        $code = $barcode ?? sprintf('EX-%05d', $seq);
        $copy = new Copy($edition, $branch, $code, $status, $this->clock->now());

        $this->em->persist($copy);
        $this->em->flush();

        return $copy;
    }
}
