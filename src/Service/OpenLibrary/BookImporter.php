<?php

declare(strict_types=1);

namespace App\Service\OpenLibrary;

use App\Entity\Contributor;
use App\Entity\Edition;
use App\Entity\EditionContributor;
use App\Entity\Work;
use App\Enum\CompletenessStatus;
use App\Enum\ContributorRole;
use App\Enum\MediaType;
use App\Repository\EditionRepository;
use Doctrine\ORM\EntityManagerInterface;

class BookImporter
{
    public function __construct(
        private readonly BookMetadataProviderInterface $metadataProvider,
        private readonly EntityManagerInterface $em,
        private readonly EditionRepository $editionRepository,
    ) {
    }

    public function import(string $rawIsbn): ImportResult
    {
        $cleanIsbn = Edition::normalizeIsbn($rawIsbn);
        if ($cleanIsbn === null || strlen($cleanIsbn) < 10) {
            return ImportResult::invalidIsbn($rawIsbn);
        }

        // DEC-005: Local data has priority, duplicates are never overwritten
        $existing = $this->editionRepository->findOneBy(['isbn' => $cleanIsbn]);
        if ($existing !== null) {
            return ImportResult::duplicateSkipped($existing);
        }

        try {
            $metadata = $this->metadataProvider->fetchByIsbn($cleanIsbn);
            if ($metadata === null) {
                return ImportResult::notFound($cleanIsbn);
            }

            // Find or create Work by title
            $workRepo = $this->em->getRepository(Work::class);
            $work = $workRepo->findOneBy(['originalTitle' => $metadata->title]);
            if ($work === null) {
                $work = new Work($metadata->title);
                $this->em->persist($work);
            }

            // Determine completeness according to DEC-005
            $completenessStatus = $metadata->isComplete
                ? CompletenessStatus::COMPLETE
                : CompletenessStatus::TO_COMPLETE;

            $publisher = !empty($metadata->publishers)
                ? $metadata->publishers[0]
                : 'Éditeur à compléter';

            $year = $metadata->publicationYear ?? (int) date('Y');

            $edition = new Edition(
                work: $work,
                title: $metadata->title,
                publisher: $publisher,
                publicationYear: $year,
                mediaType: MediaType::BOOK,
                completenessStatus: $completenessStatus,
                createdAt: new \DateTimeImmutable('now'),
                isbn: $cleanIsbn,
                coverUrl: $metadata->coverUrl
            );
            $this->em->persist($edition);

            // Add Authors
            $contribRepo = $this->em->getRepository(Contributor::class);
            $position = 1;
            foreach ($metadata->authors as $authorName) {
                $contributor = $contribRepo->findOneBy(['name' => $authorName]);
                if ($contributor === null) {
                    $contributor = new Contributor($authorName);
                    $this->em->persist($contributor);
                }

                $ec = new EditionContributor($edition, $contributor, ContributorRole::AUTHOR, $position++);
                $edition->addContributor($ec);
                $this->em->persist($ec);
            }

            $this->em->flush();

            return ImportResult::success($edition, $completenessStatus->value);
        } catch (\Throwable $e) {
            return ImportResult::error($cleanIsbn, $e->getMessage());
        }
    }
}
