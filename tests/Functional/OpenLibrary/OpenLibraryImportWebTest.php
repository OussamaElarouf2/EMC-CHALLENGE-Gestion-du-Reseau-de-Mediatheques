<?php

declare(strict_types=1);

namespace App\Tests\Functional\OpenLibrary;

use App\Entity\Edition;
use App\Entity\User;
use App\Entity\Work;
use App\Enum\CompletenessStatus;
use App\Enum\MediaType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OpenLibraryImportWebTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['edition_contributor', 'edition', 'contributor', 'work', 'member', 'app_user', 'plan', 'branch'] as $table) {
            $conn->executeStatement(sprintf('TRUNCATE TABLE `%s`', $table));
        }
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    public function testAnonymousRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin/import-isbn');

        self::assertResponseRedirects('/login');
    }

    public function testMemberCannotAccessImport(): void
    {
        $user = new User('member.noimport@mediatheque.local', 'hash', ['ROLE_MEMBER']);
        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user);
        $this->client->request('GET', '/admin/import-isbn');

        self::assertResponseStatusCodeSame(403);
    }

    public function testLibrarianCanAccessImportForm(): void
    {
        $librarian = new User('librarian.import@mediatheque.local', 'hash', ['ROLE_LIBRARIAN']);
        $this->em->persist($librarian);
        $this->em->flush();

        $this->client->loginUser($librarian);
        $this->client->request('GET', '/admin/import-isbn');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Import Enrichi Open Library');
        self::assertSelectorExists('form[action="/admin/import-isbn"]');
    }

    public function testLibrarianImportDuplicateIsbnShowsNoticeAndRedirects(): void
    {
        $librarian = new User('librarian.import2@mediatheque.local', 'hash', ['ROLE_LIBRARIAN']);
        $work = new Work('Livre Déjà Présent');
        $edition = new Edition($work, 'Livre Déjà Présent', 'Gallimard', 2020, MediaType::BOOK, CompletenessStatus::COMPLETE, new \DateTimeImmutable('2020-01-01'), '9782070612758');

        $this->em->persist($librarian);
        $this->em->persist($work);
        $this->em->persist($edition);
        $this->em->flush();

        $this->client->loginUser($librarian);
        $crawler = $this->client->request('GET', '/admin/import-isbn');

        $form = $crawler->selectButton('Interroger Open Library & Importer')->form([
            'isbn' => '978-2-07-061275-8',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/catalogue/' . $edition->getId());
        $this->client->followRedirect();

        self::assertSelectorExists('.alert-info');
        self::assertAnySelectorTextContains('.alert-info', 'ISBN déjà présent');
    }
}
