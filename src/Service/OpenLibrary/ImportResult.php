<?php

declare(strict_types=1);

namespace App\Service\OpenLibrary;

use App\Entity\Edition;

class ImportResult
{
    public const STATUS_COMPLETE = 'COMPLETE';
    public const STATUS_TO_COMPLETE = 'TO_COMPLETE';
    public const STATUS_SKIPPED = 'SKIPPED';
    public const STATUS_NOT_FOUND = 'NOT_FOUND';
    public const STATUS_INVALID = 'INVALID';
    public const STATUS_ERROR = 'ERROR';

    public function __construct(
        public readonly string $status,
        public readonly string $isbn,
        public readonly ?Edition $edition = null,
        public readonly ?string $message = null
    ) {
    }

    public static function success(Edition $edition, string $status): self
    {
        return new self(
            status: $status,
            isbn: (string) $edition->getIsbn(),
            edition: $edition,
            message: sprintf('Notice importée avec succès : « %s » (%s)', $edition->getTitle(), $status)
        );
    }

    public static function duplicateSkipped(Edition $existing): self
    {
        return new self(
            status: self::STATUS_SKIPPED,
            isbn: (string) $existing->getIsbn(),
            edition: $existing,
            message: sprintf('ISBN déjà présent dans le catalogue local : « %s » (priorité au catalogue local, import ignoré)', $existing->getTitle())
        );
    }

    public static function notFound(string $isbn): self
    {
        return new self(
            status: self::STATUS_NOT_FOUND,
            isbn: $isbn,
            message: sprintf('Aucun document trouvé sur Open Library pour l\'ISBN %s', $isbn)
        );
    }

    public static function invalidIsbn(string $isbn): self
    {
        return new self(
            status: self::STATUS_INVALID,
            isbn: $isbn,
            message: sprintf('Format d\'ISBN invalide : « %s » (attendu : 10 ou 13 chiffres)', $isbn)
        );
    }

    public static function error(string $isbn, string $error): self
    {
        return new self(
            status: self::STATUS_ERROR,
            isbn: $isbn,
            message: sprintf('Erreur lors du traitement de l\'ISBN %s : %s', $isbn, $error)
        );
    }

    public function isSuccess(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETE, self::STATUS_TO_COMPLETE], true);
    }
}
