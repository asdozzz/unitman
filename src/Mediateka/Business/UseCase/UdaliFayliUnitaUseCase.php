<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Port\FaylUnitaRepository;
use App\Mediateka\Business\Port\FileStorage;
use DomainException;

/**
 * Удаление всех файлов юнита: сначала объекты в хранилище, затем записи реестра.
 */
final class UdaliFayliUnitaUseCase
{
    public function __construct(
        private readonly FaylUnitaRepository $faylUnitaRepository,
        private readonly FileStorage $fileStorage
    ) {
    }

    public function handle(string $unitId): void
    {
        if (trim($unitId) === '') {
            throw new DomainException('mediateka.unit_id_is_empty');
        }

        $files = $this->faylUnitaRepository->najtiPoUnitu($unitId);

        foreach ($files as $file) {
            $this->fileStorage->delete($file->getStorageKey());
        }

        $this->faylUnitaRepository->udalitPoUnitu($unitId);
    }
}
