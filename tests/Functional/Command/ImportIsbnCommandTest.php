<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\Edition;
use App\Entity\Work;
use App\Enum\CompletenessStatus;
use App\Enum\MediaType;
use App\Service\OpenLibrary\BookImporter;
use App\Service\OpenLibrary\BookMetadata;
use App\Service\OpenLibrary\BookMetadataProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ImportIsbnCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $conn = $this->em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['edition_contributor', 'edition', 'contributor', 'work'] as $table) {
            $conn->executeStatement(sprintf('TRUNCATE TABLE `%s`', $table));
        }
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
    }

    public function testExecuteBatchImportFromFile(): void
    {
        // Setup 1 existing edition in DB to test duplicate skipping
        $work = new Work('Livre Existant');
        $this->em->persist($work);
        $existing = new Edition(
            $work,
            'Livre Existant',
            'Gallimard',
            2015,
            MediaType::BOOK,
            CompletenessStatus::COMPLETE,
            new \DateTimeImmutable('2015-01-01'),
            '9782070368228'
        );
        $this->em->persist($existing);
        $this->em->flush();

        // Create mock metadata provider
        $mockProvider = $this->createMock(BookMetadataProviderInterface::class);
        $mockProvider->method('fetchByIsbn')
            ->willReturnCallback(function (string $isbn): ?BookMetadata {
                if ($isbn === '9782070612758') {
                    return new BookMetadata(
                        isbn: $isbn,
                        title: 'Le Petit Prince',
                        publishers: ['Gallimard'],
                        publicationYear: 1943,
                        authors: ['Antoine de Saint-Exupéry'],
                        coverUrl: 'https://covers.openlibrary.org/b/id/123-M.jpg',
                        isComplete: true
                    );
                }
                return null;
            });

        // Instantiate BookImporter with mock provider
        $editionRepo = $this->em->getRepository(Edition::class);
        $importer = new BookImporter($mockProvider, $this->em, $editionRepo);

        // Replace service in container or pass directly to command
        $command = new \App\Command\ImportIsbnCommand($importer);

        $application = new Application(self::$kernel);
        $application->addCommand($command);

        // Create temporary test file with 3 ISBNs: 1 new valid, 1 duplicate, 1 unknown
        $tempFile = sys_get_temp_dir() . '/test_isbns_' . uniqid() . '.txt';
        $content = "9782070612758\n9782070368228\n9789999999999\n";
        file_put_contents($tempFile, $content);

        try {
            $commandTester = new CommandTester($command);
            $commandTester->execute([
                'file' => $tempFile,
                '--pause' => '0',
            ]);

            $output = $commandTester->getDisplay();

            self::assertSame(0, $commandTester->getStatusCode());
            self::assertStringContainsString('Open Library Batch ISBN Import', $output);
            self::assertStringContainsString('Found 3 ISBNs to process', $output);
            self::assertStringContainsString('COMPLETE', $output);
            self::assertStringContainsString('SKIPPED', $output);
            self::assertStringContainsString('NOT_FOUND', $output);
            self::assertStringContainsString('1 / 3 records added', $output);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
