<?php

declare(strict_types=1);

namespace App\Tests\Functional\MemberArea;

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

class MemberAreaTest extends WebTestCase
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

    private function createMemberFixture(string $email, string $cardNumber): Member
    {
        $branch = new Branch('Centrale', '10 Rue de Paris', 'Paris');
        $plan = new Plan('Standard', 3, 21, 2, 14, '1.00');
        $user = new User($email, 'hash', ['ROLE_MEMBER']);

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

    public function testMemberAreaDisplaysActiveLoansAndReservations(): void
    {
        $member = $this->createMemberFixture('alice@mediatheque.local', 'CARD-ALICE-1');
        $copy = $this->createEditionWithCopy($member->getHomeBranch(), 'Germinal', 'BC-GERMINAL');

        // 1 active loan
        $loan = new Loan($copy, $member, new \DateTimeImmutable('-5 days'), new \DateTimeImmutable('+16 days'));
        $copy->setStatus(CopyStatus::ON_LOAN);
        $this->em->persist($loan);

        // 1 active waiting reservation
        $resCopy = $this->createEditionWithCopy($member->getHomeBranch(), 'Bel-Ami', 'BC-BELAMI');
        $reservation = new Reservation(
            $resCopy->getEdition(),
            $member,
            $member->getHomeBranch(),
            ReservationStatus::WAITING,
            new \DateTimeImmutable('-2 days')
        );
        $this->em->persist($reservation);
        $this->em->flush();

        $this->client->loginUser($member->getUser());
        $this->client->request('GET', '/espace-adherent');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Espace Adhérent');
        self::assertAnySelectorTextContains('.table', 'Germinal');
        self::assertAnySelectorTextContains('.table', 'BC-GERMINAL');
        self::assertAnySelectorTextContains('.table', 'Bel-Ami');
        self::assertAnySelectorTextContains('.badge', 'En attente (Rang : 1er)');
    }

    public function testMemberCanExtendLoanSuccessfully(): void
    {
        $member = $this->createMemberFixture('bob@mediatheque.local', 'CARD-BOB-1');
        $copy = $this->createEditionWithCopy($member->getHomeBranch(), 'Madame Bovary', 'BC-BOVARY');

        $initialDue = new \DateTimeImmutable('+10 days');
        $loan = new Loan($copy, $member, new \DateTimeImmutable('-11 days'), $initialDue);
        $copy->setStatus(CopyStatus::ON_LOAN);
        $this->em->persist($loan);
        $this->em->flush();

        $this->client->loginUser($member->getUser());
        $crawler = $this->client->request('GET', '/espace-adherent');

        $form = $crawler->selectButton('Prolonger')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/espace-adherent');
        $this->client->followRedirect();

        self::assertSelectorExists('.alert-success');
        self::assertAnySelectorTextContains('.alert-success', 'Madame Bovary');

        $loan = static::getContainer()->get(EntityManagerInterface::class)->find(Loan::class, $loan->getId());
        self::assertNotNull($loan);
        self::assertSame(1, $loan->getExtensionCount());
        self::assertTrue($loan->getDueDate() > $initialDue);
    }

    public function testMemberCannotExtendLoanIfWaitingReservationExists(): void
    {
        $memberBob = $this->createMemberFixture('bob2@mediatheque.local', 'CARD-BOB-2');
        $memberAlice = $this->createMemberFixture('alice2@mediatheque.local', 'CARD-ALICE-2');

        $copy = $this->createEditionWithCopy($memberBob->getHomeBranch(), 'L\'Étranger', 'BC-ETRANGER');

        $loan = new Loan($copy, $memberBob, new \DateTimeImmutable('-5 days'), new \DateTimeImmutable('+16 days'));
        $copy->setStatus(CopyStatus::ON_LOAN);
        $this->em->persist($loan);

        // Alice puts a waiting reservation on the edition
        $reservation = new Reservation(
            $copy->getEdition(),
            $memberAlice,
            $memberAlice->getHomeBranch(),
            ReservationStatus::WAITING,
            new \DateTimeImmutable('now')
        );
        $this->em->persist($reservation);
        $this->em->flush();

        $this->client->loginUser($memberBob->getUser());
        $crawler = $this->client->request('GET', '/espace-adherent');

        $form = $crawler->selectButton('Prolonger')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/espace-adherent');
        $this->client->followRedirect();

        self::assertSelectorExists('.alert-danger');

        $loan = static::getContainer()->get(EntityManagerInterface::class)->find(Loan::class, $loan->getId());
        self::assertNotNull($loan);
        self::assertSame(0, $loan->getExtensionCount());
    }

    public function testMemberCanCancelReservation(): void
    {
        $member = $this->createMemberFixture('charlie@mediatheque.local', 'CARD-CHARLIE-1');
        $copy = $this->createEditionWithCopy($member->getHomeBranch(), 'Le Rouge et le Noir', 'BC-ROUGE');

        $reservation = new Reservation(
            $copy->getEdition(),
            $member,
            $member->getHomeBranch(),
            ReservationStatus::WAITING,
            new \DateTimeImmutable('now')
        );
        $this->em->persist($reservation);
        $this->em->flush();

        $this->client->loginUser($member->getUser());
        $crawler = $this->client->request('GET', '/espace-adherent');

        $form = $crawler->selectButton('Annuler')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/espace-adherent');
        $this->client->followRedirect();

        self::assertSelectorExists('.alert-success');

        $reservation = static::getContainer()->get(EntityManagerInterface::class)->find(Reservation::class, $reservation->getId());
        self::assertNotNull($reservation);
        self::assertSame(ReservationStatus::CANCELLED, $reservation->getStatus());
    }

    public function testMemberCannotExtendAnotherMembersLoan(): void
    {
        $memberBob = $this->createMemberFixture('bob3@mediatheque.local', 'CARD-BOB-3');
        $memberAlice = $this->createMemberFixture('alice3@mediatheque.local', 'CARD-ALICE-3');

        $copy = $this->createEditionWithCopy($memberBob->getHomeBranch(), 'Vol de Nuit', 'BC-VOL');

        $loan = new Loan($copy, $memberBob, new \DateTimeImmutable('-5 days'), new \DateTimeImmutable('+16 days'));
        $copy->setStatus(CopyStatus::ON_LOAN);
        $this->em->persist($loan);
        $this->em->flush();

        // Alice logs in and attempts to POST extend Bob's loan
        $this->client->loginUser($memberAlice->getUser());
        $this->client->request('POST', '/espace-adherent/emprunts/' . $loan->getId() . '/prolonger');

        self::assertResponseStatusCodeSame(403);
    }
}
