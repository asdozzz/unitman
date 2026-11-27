<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Port\FaylUnitaRepository;
use App\Mediateka\Business\Port\FileStorage;
use DomainException;

/**
 * Читает содержимое файла хода с ограничением размера.
 *
 * Файл скачивается во временный файл, проверяется кодировка и NUL-байты,
 * временный файл удаляется в `finally`.
 */
final class PoluchitSoderzhimoeFajlaQuery
{
    public function __construct(
        private readonly FaylUnitaRepository $faylUnitaRepository,
        private readonly FileStorage $fileStorage,
    ) {
    }

    public function handle(string $fileId, int $maxBytes): string
    {
        $file = $this->faylUnitaRepository->findById($fileId);

        if ($file === null) {
            throw new DomainException('mediateka.file.not_found');
        }

        if ($file->getSize() > $maxBytes) {
            throw new DomainException('mediateka.file.too_large');
        }

        $tempPath = $this->fileStorage->downloadToTemp($file->getStorageKey(), $maxBytes);

        try {
            $content = file_get_contents($tempPath);

            if ($content === false) {
                throw new DomainException('mediateka.file.not_found');
            }
        } finally {
            @unlink($tempPath);
        }

        if (strlen($content) > $maxBytes) {
            throw new DomainException('mediateka.file.too_large');
        }

        if (str_contains($content, "\0") || !mb_check_encoding($content, 'UTF-8')) {
            throw new DomainException('mediateka.file.content_is_invalid');
        }

        return $content;
    }
}
