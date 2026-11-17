<?php

declare(strict_types=1);

namespace App\Service\OpenLibrary;

interface BookMetadataProviderInterface
{
    /**
     * Fetches bibliographic metadata from Open Library for a given ISBN.
     * Returns null if not found or if the ISBN is invalid.
     */
    public function fetchByIsbn(string $isbn): ?BookMetadata;
}
