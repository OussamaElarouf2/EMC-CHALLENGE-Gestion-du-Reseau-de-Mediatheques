<?php

declare(strict_types=1);

namespace App\Tests\Functional\Circulation;

use App\Entity\Branch;
use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\Loan;
use App\Entity\Member;
use App\Entity\Plan;
use App\Entity\Reservation;
use App\Entity\User;
use App\Entity\Work;
use App\Enum\CompletenessStatus;
use App\Enum\CopyStatus;
use App\Enum\MediaType;
use App\Enum\ReservationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LibrarianDeskTest extends WebTestCase
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

    private function createLibrarian(): User
    {
        $user = new User('librarian.desk@mediatheque.local', 'hash', ['ROLE_LIBRARIAN']);
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function createMemberFixture(string $cardNumber): Member
    {
        $branch = new Branch('Centrale', '10 Rue de Paris', 'Paris');
        $plan = new Plan('Standard', 3, 21, 2, 14, '1.00');
        $user = new User('member.' . $cardNumber . '@mediatheque.local', 'hash', ['ROLE_MEMBER']);

        $member = new Member($user, $plan, $branch, $cardNumber, new \DateTimeImmutable('+1 year'), new \DateTimeImmutable('now'));

        $this->em->persist($branch);
        $this->em->persist($plan);
        $this->em->persist($user);
        $this->em->persist($member);
        $this->em->flush();

        return $member;
    }

    private function createEditionWithCopy(Branch $branch, string $title, string $barcode): Copy
    {
        $work = new Work($title);
        $this->em->persist($work);

        $edition = new Edition($work, $title, 'Gallimard', 2020, MediaType::BOOK, CompletenessStatus::COMPLETE, new \DateTimeImmutable('2020-01-01'));
        $this->em->persist($edition);

        $copy = new Copy($edition, $branch, $barcode, CopyStatus::AVAILABLE, new \DateTimeImmutable('now'));
        $this->em->persist($copy);
        $this->em->flush();

        return $copy;
    }

    public function testLibrarianCanAccessDesk(): void
    {
        $librarian = $this->createLibrarian();
        $this->client->loginUser($librarian);

        $this->client->request('GET', '/admin/guichet');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Guichet Circulation');
        self::assertSelectorExists('form[action="/admin/guichet/emprunt"]');
        self::assertSelectorExists('form[action="/admin/guichet/retour"]');
    }

    public function testMemberCannotAccessDesk(): void
    {
        $member = $this->createMemberFixture('CARD-FORBIDDEN');
        $this->client->loginUser($member->getUser());

        $this->client->request('GET', '/admin/guichet');

        self::assertResponseStatusCodeSame(403);
    }

    public function testLibrarianCanBorrowAtDesk(): void
    {
        $librarian = $this->createLibrarian();
        $member = $this->createMemberFixture('CARD-DESK-BORROW');
        $copy = $this->createEditionWithCopy($member->getHomeBranch(), 'Les Fleurs du Mal', 'BC-FLEURS');

        $this->client->loginUser($librarian);
        $crawler = $this->client->request('GET', '/admin/guichet');

        $form = $crawler->selectButton('Valider l\'emprunt')->form([
            'membershipNumber' => 'CARD-DESK-BORROW',
            'barcode' => 'BC-FLEURS',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/guichet');
        $this->client->followRedirect();

        self::assertSelectorExists('.alert-success');
        self::assertAnySelectorTextContains('.alert-success', 'Les Fleurs du Mal');

        $copy = static::getContainer()->get(EntityManagerInterface::class)->find(Copy::class, $copy->getId());
        self::assertNotNull($copy);
        self::assertSame(CopyStatus::ON_LOAN, $copy->getStatus());
    }

    public function testLibrarianCanReturnAtDesk(): void
    {
        $librarian = $this->createLibrarian();
        $member = $this->createMemberFixture('CARD-DESK-RETURN');
        $copy = $this->createEditionWithCopy($member->getHomeBranch(), 'Notre-Dame de Paris', 'BC-NOTREDAME');

        // Setup active loan
        $loan = new Loan($copy, $member, new \DateTimeImmutable('-5 days'), new \DateTimeImmutable('+16 days'));
        $copy->setStatus(CopyStatus::ON_LOAN);
        $this->em->persist($loan);
        $this->em->flush();

        $this->client->loginUser($librarian);
        $crawler = $this->client->request('GET', '/admin/guichet');

        $form = $crawler->selectButton('Valider le retour')->form([
            'barcode' => 'BC-NOTREDAME',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/guichet');
        $this->client->followRedirect();

        self::assertSelectorExists('.alert-success');
        self::assertAnySelectorTextContains('.alert-success', 'Notre-Dame de Paris');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $loan = $em->find(Loan::class, $loan->getId());
        $copy = $em->find(Copy::class, $copy->getId());
        self::assertNotNull($loan);
        self::assertNotNull($copy);
        self::assertNotNull($loan->getReturnedAt());
        self::assertSame(CopyStatus::AVAILABLE, $copy->getStatus());
    }

    public function testLibrarianReturnOverdueTriggersLatePenalty(): void
    {
        $librarian = $this->createLibrarian();
        $member = $this->createMemberFixture('CARD-DESK-PENALTY');
        $copy = $this->createEditionWithCopy($member->getHomeBranch(), 'Candide', 'BC-CANDIDE');

        // Due date 5 days ago -> 5 days overdue * 1.00 DH = 5.00 DH
        $loan = new Loan($copy, $member, new \DateTimeImmutable('-26 days'), new \DateTimeImmutable('-5 days'));
        $copy->setStatus(CopyStatus::ON_LOAN);
        $this->em->persist($loan);
        $this->em->flush();

        $this->client->loginUser($librarian);
        $crawler = $this->client->request('GET', '/admin/guichet');

        $form = $crawler->selectButton('Valider le retour')->form([
            'barcode' => 'BC-CANDIDE',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/guichet');
        $this->client->followRedirect();

        self::assertSelectorExists('.alert-success');
        self::assertAnySelectorTextContains('.alert-success', 'pénalité de 5.00 DH');

        $loan = static::getContainer()->get(EntityManagerInterface::class)->find(Loan::class, $loan->getId());
        self::assertNotNull($loan);
        self::assertNotNull($loan->getReturnedAt());
    }
}
