<?php

declare(strict_types=1);

namespace App\Service\OpenLibrary;

class BookMetadata
{
    /**
     * @param list<string> $publishers
     * @param list<string> $authors
     */
    public function __construct(
        public readonly string $isbn,
        public readonly string $title,
        public readonly array $publishers,
        public readonly ?int $publicationYear,
        public readonly array $authors,
        public readonly ?string $coverUrl,
        public readonly bool $isComplete
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromOpenLibraryResponse(string $isbn, array $data): self
    {
        $title = trim((string) ($data['title'] ?? 'Titre inconnu'));

        $publishers = [];
        if (isset($data['publishers']) && is_array($data['publishers'])) {
            foreach ($data['publishers'] as $pub) {
                if (is_array($pub) && isset($pub['name'])) {
                    $publishers[] = trim((string) $pub['name']);
                }
            }
        }

        $authors = [];
        if (isset($data['authors']) && is_array($data['authors'])) {
            foreach ($data['authors'] as $author) {
                if (is_array($author) && isset($author['name'])) {
                    $authors[] = trim((string) $author['name']);
                }
            }
        }

        // Extract 4-digit publication year
        $publicationYear = null;
        if (isset($data['publish_date']) && is_string($data['publish_date'])) {
            if (preg_match('/\b(19\d\d|20\d\d)\b/', $data['publish_date'], $matches)) {
                $publicationYear = (int) $matches[1];
            }
        }
        if ($publicationYear === null) {
            $publicationYear = (int) date('Y');
        }

        // Cover URL
        $coverUrl = null;
        if (isset($data['cover']) && is_array($data['cover'])) {
            $coverUrl = $data['cover']['medium'] ?? $data['cover']['large'] ?? $data['cover']['small'] ?? null;
            if (is_string($coverUrl) && trim($coverUrl) === '') {
                $coverUrl = null;
            }
        }

        // Completeness evaluation
        $hasTitle = $title !== '' && $title !== 'Titre inconnu';
        $hasPublisher = !empty($publishers);
        $hasAuthor = !empty($authors);
        $hasCover = $coverUrl !== null;

        $isComplete = $hasTitle && $hasPublisher && $hasAuthor && $hasCover;

        return new self(
            isbn: $isbn,
            title: $title,
            publishers: $publishers,
            publicationYear: $publicationYear,
            authors: $authors,
            coverUrl: $coverUrl,
            isComplete: $isComplete
        );
    }
}
