<?php

declare(strict_types=1);

namespace App\Tests\Functional\Statistics;

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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class StatisticsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $this->purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    private function purgeDatabase(): void
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

    public function testAnonymousRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin/statistiques');

        self::assertResponseRedirects('/login');
    }

    public function testMemberCannotAccessStatistics(): void
    {
        $user = new User('member.stats@mediatheque.local', 'hash', ['ROLE_MEMBER']);
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/statistiques');

        self::assertResponseStatusCodeSame(403);
    }

    public function testLibrarianCanAccessStatisticsDashboard(): void
    {
        $librarian = new User('librarian.stats@mediatheque.local', 'hash', ['ROLE_LIBRARIAN']);
        $branch = new Branch('Centrale', '10 Rue de Paris', 'Paris');
        $plan = new Plan('Standard', 3, 21, 2, 14, '1.00');

        $memberUser = new User('member.stats2@mediatheque.local', 'hash', ['ROLE_MEMBER']);
        $member = new Member($memberUser, $plan, $branch, 'CARD-STAT-1', new \DateTimeImmutable('+1 year'), new \DateTimeImmutable('now'));

        $work = new Work('Le Comte de Monte-Cristo');
        $edition = new Edition($work, 'Le Comte de Monte-Cristo', 'Gallimard', 2020, MediaType::BOOK, CompletenessStatus::COMPLETE, new \DateTimeImmutable('2020-01-01'));
        $copy = new Copy($edition, $branch, 'BC-MONTE-1', CopyStatus::ON_LOAN, new \DateTimeImmutable('now'));

        $loan = new Loan($copy, $member, new \DateTimeImmutable('-3 days'), new \DateTimeImmutable('+18 days'));

        $this->em->persist($librarian);
        $this->em->persist($branch);
        $this->em->persist($plan);
        $this->em->persist($memberUser);
        $this->em->persist($member);
        $this->em->persist($work);
        $this->em->persist($edition);
        $this->em->persist($copy);
        $this->em->persist($loan);
        $this->em->flush();

        $this->client->loginUser($librarian);
        $this->client->request('GET', '/admin/statistiques');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Statistiques & Indicateurs de Circulation');

        // Check KPI cards
        self::assertAnySelectorTextContains('.card', 'Taux de rotation global');
        self::assertAnySelectorTextContains('.card', 'Fonds total du réseau');
        self::assertAnySelectorTextContains('.card', 'Volume total d\'emprunts');
        self::assertAnySelectorTextContains('.card', 'Adhérents actifs');

        // Check Sections
        self::assertAnySelectorTextContains('.card-header', 'Taux de rotation par support');
        self::assertAnySelectorTextContains('.card-header', 'Taux de rotation par site médiathèque');
        self::assertAnySelectorTextContains('.card-header', 'Top 10 : Les documents les plus empruntés');
        self::assertAnySelectorTextContains('.table', 'Le Comte de Monte-Cristo');
    }
}
