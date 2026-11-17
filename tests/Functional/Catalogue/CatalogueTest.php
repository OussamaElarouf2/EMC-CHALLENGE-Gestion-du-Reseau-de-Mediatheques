<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalogue;

use App\Entity\Branch;
use App\Entity\Contributor;
use App\Entity\Copy;
use App\Entity\Edition;
use App\Entity\EditionContributor;
use App\Entity\Member;
use App\Entity\Plan;
use App\Entity\User;
use App\Entity\Work;
use App\Enum\CompletenessStatus;
use App\Enum\ContributorRole;
use App\Enum\CopyStatus;
use App\Enum\MediaType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CatalogueTest extends WebTestCase
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

    private function createEditionFixture(
        string $title,
        MediaType $type = MediaType::BOOK,
        ?string $isbn = null,
        ?string $authorName = null
    ): Edition {
        $work = new Work($title);
        $this->em->persist($work);

        $edition = new Edition(
            $work,
            $title,
            'Gallimard',
            2021,
            $type,
            CompletenessStatus::COMPLETE,
            new \DateTimeImmutable('2021-01-01'),
            $isbn
        );
        $this->em->persist($edition);

        if ($authorName !== null) {
            $contributor = new Contributor($authorName);
            $this->em->persist($contributor);

            $ec = new EditionContributor($edition, $contributor, ContributorRole::AUTHOR, 1);
            $edition->addContributor($ec);
            $this->em->persist($ec);
        }

        $this->em->flush();
        $this->em->clear();

        return $edition;
    }

    public function testCatalogueIndexRendersSuccessfully(): void
    {
        $this->createEditionFixture('Les Misérables', MediaType::BOOK, '9782070409228', 'Victor Hugo');

        $this->client->request('GET', '/catalogue');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Catalogue du Réseau');
        self::assertSelectorExists('form[action="/catalogue"]');
        self::assertAnySelectorTextContains('.catalogue-card', 'Les Misérables');
        self::assertAnySelectorTextContains('.catalogue-card', 'Victor Hugo');
    }

    public function testCatalogueSearchByTitle(): void
    {
        $this->createEditionFixture('Le Petit Prince', MediaType::BOOK, '9782070612758', 'Antoine de Saint-Exupéry');
        $this->createEditionFixture('Dune', MediaType::BOOK, '9782266320481', 'Frank Herbert');

        $this->client->request('GET', '/catalogue?q=Prince');

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('.catalogue-card', 'Le Petit Prince');
        self::assertStringNotContainsString('Frank Herbert', (string) $this->client->getResponse()->getContent());
    }

    public function testCatalogueSearchByIsbnWithoutHyphens(): void
    {
        $this->createEditionFixture('Fondation', MediaType::BOOK, '978-2-07-036053-6', 'Isaac Asimov');

        $this->client->request('GET', '/catalogue?q=9782070360536');

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('.catalogue-card', 'Fondation');
    }

    public function testCatalogueFilterByMediaType(): void
    {
        $this->createEditionFixture('Astérix le Gaulois', MediaType::COMIC, '9782012101333', 'René Goscinny');
        $this->createEditionFixture('Inception', MediaType::DVD, null, 'Christopher Nolan');

        $this->client->request('GET', '/catalogue?type=DVD');

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('.catalogue-card', 'Inception');
        self::assertStringNotContainsString('Astérix le Gaulois', (string) $this->client->getResponse()->getContent());
    }

    public function testEditionDetailShowsAvailabilityPerBranch(): void
    {
        $edition = $this->createEditionFixture('1984', MediaType::BOOK, '9782070368228', 'George Orwell');
        $managedEdition = $this->em->find(Edition::class, $edition->getId());

        $centrale = new Branch('Médiathèque Centrale', '10 Rue de Paris', 'Paris');
        $annexe = new Branch('Médiathèque Annexe', '5 Avenue Victor Hugo', 'Paris');
        $this->em->persist($centrale);
        $this->em->persist($annexe);

        // Centrale: 2 copies (1 available, 1 on loan)
        $c1 = new Copy($managedEdition, $centrale, 'BARCODE-C1', CopyStatus::AVAILABLE, new \DateTimeImmutable('now'));
        $c2 = new Copy($managedEdition, $centrale, 'BARCODE-C2', CopyStatus::ON_LOAN, new \DateTimeImmutable('now'));

        // Annexe: 1 copy (available)
        $c3 = new Copy($managedEdition, $annexe, 'BARCODE-A1', CopyStatus::AVAILABLE, new \DateTimeImmutable('now'));

        $this->em->persist($c1);
        $this->em->persist($c2);
        $this->em->persist($c3);
        $this->em->flush();
        $this->em->clear();

        $crawler = $this->client->request('GET', '/catalogue/' . $edition->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', '1984');
        self::assertAnySelectorTextContains('.table', 'George Orwell');

        // Check availability per branch
        self::assertAnySelectorTextContains('h3', 'Médiathèque Centrale');
        self::assertAnySelectorTextContains('h3', 'Médiathèque Annexe');

        // Centrale has 1 available out of 2
        self::assertAnySelectorTextContains('.badge', '1 disponible(s)');
        self::assertAnySelectorTextContains('.small', '2 exemplaire(s) rattaché(s)');
    }

    public function testAnonymousPromptedToLoginForReservation(): void
    {
        $edition = $this->createEditionFixture('Solaris', MediaType::BOOK, '9782070412143', 'Stanislas Lem');

        $this->client->request('GET', '/catalogue/' . $edition->getId());

        self::assertResponseIsSuccessful();
        self::assertAnySelectorTextContains('.card', 'Se connecter pour réserver');
        self::assertSelectorExists('a[href="/login"]');
    }

    public function testActiveMemberCanReserveFromCatalogue(): void
    {
        $edition = $this->createEditionFixture('Dune Messiah', MediaType::BOOK, '9782266320498', 'Frank Herbert');
        $managedEdition = $this->em->find(Edition::class, $edition->getId());

        $centrale = new Branch('Médiathèque Centrale', '10 Rue de Paris', 'Paris');
        $annexe = new Branch('Médiathèque Annexe', '5 Avenue Victor Hugo', 'Paris');
        $plan = new Plan('Standard', 3, 21, 1, 14, '1.00');

        $user = new User('member.test@mediatheque.local', 'hash', ['ROLE_MEMBER']);
        $member = new Member($user, $plan, $centrale, 'CARD-RES-1', new \DateTimeImmutable('+1 month'), new \DateTimeImmutable('now'));

        // Copy is on loan so reservation will be WAITING in queue
        $copy = new Copy($managedEdition, $centrale, 'BARCODE-DM-1', CopyStatus::ON_LOAN, new \DateTimeImmutable('now'));

        $this->em->persist($centrale);
        $this->em->persist($annexe);
        $this->em->persist($plan);
        $this->em->persist($user);
        $this->em->persist($member);
        $this->em->persist($copy);
        $this->em->flush();

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/catalogue/' . $edition->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/catalogue/' . $edition->getId() . '/reserve"]');

        $form = $crawler->selectButton('Confirmer la réservation')->form([
            'pickupBranchId' => (string) $annexe->getId(),
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/catalogue/' . $edition->getId());
        $this->client->followRedirect();

        self::assertSelectorExists('.alert-success');
        self::assertAnySelectorTextContains('.alert-success', 'Médiathèque Annexe');
    }
}
