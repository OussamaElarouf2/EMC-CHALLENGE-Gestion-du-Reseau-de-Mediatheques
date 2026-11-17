<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\OpenLibrary;

use App\Service\OpenLibrary\OpenLibraryClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OpenLibraryClientTest extends TestCase
{
    public function testFetchByIsbnReturnsCompleteMetadata(): void
    {
        $isbn = '9782070612758';
        $json = json_encode([
            'ISBN:' . $isbn => [
                'title' => 'Le Petit Prince',
                'publishers' => [['name' => 'Gallimard']],
                'publish_date' => '1943',
                'authors' => [['name' => 'Antoine de Saint-Exupéry']],
                'cover' => [
                    'medium' => 'https://covers.openlibrary.org/b/id/12345-M.jpg',
                ],
            ],
        ]);

        $mockResponse = new MockResponse((string) $json, ['http_code' => 200]);
        $httpClient = new MockHttpClient([$mockResponse]);
        $client = new OpenLibraryClient($httpClient);

        $metadata = $client->fetchByIsbn($isbn);

        self::assertNotNull($metadata);
        self::assertSame($isbn, $metadata->isbn);
        self::assertSame('Le Petit Prince', $metadata->title);
        self::assertSame(['Gallimard'], $metadata->publishers);
        self::assertSame(1943, $metadata->publicationYear);
        self::assertSame(['Antoine de Saint-Exupéry'], $metadata->authors);
        self::assertSame('https://covers.openlibrary.org/b/id/12345-M.jpg', $metadata->coverUrl);
        self::assertTrue($metadata->isComplete);
    }

    public function testFetchByIsbnReturnsIncompleteMetadataWhenFieldsMissing(): void
    {
        $isbn = '9782070000000';
        $json = json_encode([
            'ISBN:' . $isbn => [
                'title' => 'Titre Sans Auteur Ni Editeur',
            ],
        ]);

        $mockResponse = new MockResponse((string) $json, ['http_code' => 200]);
        $httpClient = new MockHttpClient([$mockResponse]);
        $client = new OpenLibraryClient($httpClient);

        $metadata = $client->fetchByIsbn($isbn);

        self::assertNotNull($metadata);
        self::assertSame('Titre Sans Auteur Ni Editeur', $metadata->title);
        self::assertEmpty($metadata->publishers);
        self::assertEmpty($metadata->authors);
        self::assertNull($metadata->coverUrl);
        self::assertFalse($metadata->isComplete);
    }

    public function testFetchByIsbnReturnsNullWhenNotFound(): void
    {
        $isbn = '9780000000000';
        $mockResponse = new MockResponse('{}', ['http_code' => 200]);
        $httpClient = new MockHttpClient([$mockResponse]);
        $client = new OpenLibraryClient($httpClient);

        $metadata = $client->fetchByIsbn($isbn);

        self::assertNull($metadata);
    }

    public function testFetchByIsbnReturnsNullOnHttpError(): void
    {
        $isbn = '9780000000000';
        $mockResponse = new MockResponse('Server Error', ['http_code' => 500]);
        $httpClient = new MockHttpClient([$mockResponse]);
        $client = new OpenLibraryClient($httpClient);

        $metadata = $client->fetchByIsbn($isbn);

        self::assertNull($metadata);
    }

    public function testFetchByIsbnReturnsNullOnInvalidIsbnFormatWithoutCallingApi(): void
    {
        $httpClient = new MockHttpClient([]);
        $client = new OpenLibraryClient($httpClient);

        $metadata = $client->fetchByIsbn('INVALID-ISBN-123');

        self::assertNull($metadata);
        self::assertSame(0, $httpClient->getRequestsCount());
    }
}
