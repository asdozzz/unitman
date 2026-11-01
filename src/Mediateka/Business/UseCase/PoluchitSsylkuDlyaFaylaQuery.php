<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Port\FaylUnitaRepository;
use App\Mediateka\Business\Port\FileStorage;

final class PoluchitSsylkuDlyaFaylaQuery
{
    public function __construct(
        private readonly FaylUnitaRepository $faylUnitaRepository,
        private readonly FileStorage $fileStorage
    ) {
    }

    public function handle(string $fileId, ?int $ttl = null): string
    {
        $file = $this->faylUnitaRepository->getById($fileId);

        return $this->fileStorage->presignedGetUrl(
            $file->getStorageKey(),
            $file->getName(),
            $ttl
        );
    }
}
