<?php

declare(strict_types=1);

namespace App\Service\OpenLibrary;

use App\Entity\Edition;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenLibraryClient implements BookMetadataProviderInterface
{
    public const API_BASE_URL = 'https://openlibrary.org/api/books';
    public const USER_AGENT = 'MediathequePFE/1.0 (pfe@educationmedia.ma)';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function fetchByIsbn(string $isbn): ?BookMetadata
    {
        $cleanIsbn = Edition::normalizeIsbn($isbn);
        if ($cleanIsbn === null || !$this->isValidIsbnFormat($cleanIsbn)) {
            $this->logger->warning(sprintf('[OpenLibrary] Invalid ISBN format: %s', $isbn));
            return null;
        }

        $bibkey = 'ISBN:' . $cleanIsbn;
        $url = sprintf('%s?bibkeys=%s&format=json&jscmd=data', self::API_BASE_URL, urlencode($bibkey));

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'application/json',
                ],
                'timeout' => 5.0,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                $this->logger->warning(sprintf('[OpenLibrary] HTTP %d received for ISBN %s', $statusCode, $cleanIsbn));
                return null;
            }

            $content = $response->toArray(false);
            if (!isset($content[$bibkey]) || !is_array($content[$bibkey])) {
                $this->logger->info(sprintf('[OpenLibrary] Book not found for ISBN %s', $cleanIsbn));
                return null;
            }

            return BookMetadata::fromOpenLibraryResponse($cleanIsbn, $content[$bibkey]);
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('[OpenLibrary] Network error for ISBN %s: %s', $cleanIsbn, $e->getMessage()), [
                'exception' => $e,
            ]);
            return null;
        }
    }

    /**
     * Validates that ISBN is composed of 10 or 13 alphanumeric/digit characters.
     */
    private function isValidIsbnFormat(string $isbn): bool
    {
        $len = strlen($isbn);
        if ($len === 10) {
            return (bool) preg_match('/^[0-9]{9}[0-9Xx]$/', $isbn);
        }
        if ($len === 13) {
            return (bool) preg_match('/^[0-9]{13}$/', $isbn);
        }

        return false;
    }
}
