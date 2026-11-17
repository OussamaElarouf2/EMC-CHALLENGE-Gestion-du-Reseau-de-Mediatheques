<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\OpenLibrary;

use App\Entity\Edition;
use App\Entity\Work;
use App\Enum\CompletenessStatus;
use App\Enum\MediaType;
use App\Repository\EditionRepository;
use App\Service\OpenLibrary\BookImporter;
use App\Service\OpenLibrary\BookMetadata;
use App\Service\OpenLibrary\BookMetadataProviderInterface;
use App\Service\OpenLibrary\ImportResult;
use App\Tests\Integration\BaseIntegrationTestCase;

class BookImporterTest extends BaseIntegrationTestCase
{
    private BookMetadataProviderInterface $mockProvider;
    private BookImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockProvider = $this->createMock(BookMetadataProviderInterface::class);
        $editionRepo = $this->em->getRepository(Edition::class);
        $this->importer = new BookImporter($this->mockProvider, $this->em, $editionRepo);
    }

    public function testImportCompleteBookSuccess(): void
    {
        $isbn = '9782070612758';
        $metadata = new BookMetadata(
            isbn: $isbn,
            title: 'Le Petit Prince',
            publishers: ['Gallimard Jeunesse'],
            publicationYear: 1943,
            authors: ['Antoine de Saint-Exupéry'],
            coverUrl: 'https://covers.openlibrary.org/b/id/12345-M.jpg',
            isComplete: true
        );

        $this->mockProvider->method('fetchByIsbn')
            ->with($isbn)
            ->willReturn($metadata);

        $result = $this->importer->import($isbn);

        self::assertTrue($result->isSuccess());
        self::assertSame(ImportResult::STATUS_COMPLETE, $result->status);
        self::assertNotNull($result->edition);
        self::assertSame('Le Petit Prince', $result->edition->getTitle());
        self::assertSame(CompletenessStatus::COMPLETE, $result->edition->getCompletenessStatus());
        self::assertCount(1, $result->edition->getContributors());
    }

    public function testImportIncompleteBookFlagsToComplete(): void
    {
        $isbn = '9782070000001';
        $metadata = new BookMetadata(
            isbn: $isbn,
            title: 'Notice Incomplète',
            publishers: [],
            publicationYear: null,
            authors: [],
            coverUrl: null,
            isComplete: false
        );

        $this->mockProvider->method('fetchByIsbn')
            ->with($isbn)
            ->willReturn($metadata);

        $result = $this->importer->import($isbn);

        self::assertTrue($result->isSuccess());
        self::assertSame(ImportResult::STATUS_TO_COMPLETE, $result->status);
        self::assertNotNull($result->edition);
        self::assertSame(CompletenessStatus::TO_COMPLETE, $result->edition->getCompletenessStatus());
    }

    public function testImportDuplicateIsbnIsSkipped(): void
    {
        // Existing edition in DB
        $work = new Work('Oeuvre Existante');
        $this->em->persist($work);
        $existing = new Edition(
            $work,
            'Oeuvre Existante',
            'Seuil',
            2010,
            MediaType::BOOK,
            CompletenessStatus::COMPLETE,
            new \DateTimeImmutable('2010-01-01'),
            '9782020282888'
        );
        $this->em->persist($existing);
        $this->em->flush();

        // Provider should never be called when ISBN is already local
        $this->mockProvider->expects(self::never())->method('fetchByIsbn');

        $result = $this->importer->import('978-2-02-028288-8');

        self::assertSame(ImportResult::STATUS_SKIPPED, $result->status);
        self::assertSame($existing->getId(), $result->edition?->getId());
    }

    public function testImportNotFoundReturnsNotFoundStatus(): void
    {
        $isbn = '9789999999999';
        $this->mockProvider->method('fetchByIsbn')
            ->with($isbn)
            ->willReturn(null);

        $result = $this->importer->import($isbn);

        self::assertSame(ImportResult::STATUS_NOT_FOUND, $result->status);
        self::assertNull($result->edition);
    }
}
