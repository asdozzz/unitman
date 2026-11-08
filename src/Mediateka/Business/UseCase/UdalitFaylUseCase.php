<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Port\FaylUnitaRepository;
use App\Mediateka\Business\Port\FileLinkChecker;
use App\Mediateka\Business\Port\FileStorage;
use DomainException;

final class UdalitFaylUseCase
{
    /**
     * @param iterable<FileLinkChecker> $linkCheckers
     */
    public function __construct(
        private readonly FaylUnitaRepository $faylUnitaRepository,
        private readonly FileStorage $fileStorage,
        private readonly iterable $linkCheckers = []
    ) {
    }

    public function handle(string $fileId): void
    {
        $file = $this->faylUnitaRepository->getById($fileId);

        foreach ($this->linkCheckers as $linkChecker) {
            if ($linkChecker->esliFaylPrivyazan($file->getId())) {
                throw new DomainException('mediateka.file.is_linked');
            }
        }

        $this->fileStorage->delete($file->getStorageKey());
        $this->faylUnitaRepository->udalit($file->getId());
    }

    /**
     * Удаление файла юнита без проверки принадлежности — используется при каскадном удалении.
     */
    public function udalitBezProverki(string $fileId): void
    {
        $file = $this->faylUnitaRepository->findById($fileId);
        if ($file === null) {
            throw new DomainException('mediateka.file.not_found');
        }

        $this->fileStorage->delete($file->getStorageKey());
        $this->faylUnitaRepository->udalit($file->getId());
    }
}
