<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Command\ZagruzitFaylUnita;
use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\CanGenerateGuid;
use App\Mediateka\Business\Port\FaylUnitaRepository;
use App\Mediateka\Business\Port\FileStorage;
use App\Mediateka\Business\Utils\ZipArhivValidator;
use DomainException;

final class ZagruzitFaylUnitaUseCase
{
    public const NAME_MAX_LENGTH = 255;

    public function __construct(
        private readonly FaylUnitaRepository $faylUnitaRepository,
        private readonly FileStorage $fileStorage,
        private readonly CanGenerateGuid $canGenerateGuid,
        private readonly int $maxFileSize
    ) {
    }

    public function handle(ZagruzitFaylUnita $command): FaylUnita
    {
        $unitId = $this->opredelitUnitId($command->unitId);

        $name = trim($command->name);
        if ($name === '') {
            throw new DomainException('mediateka.upload.name_is_empty');
        }
        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new DomainException('mediateka.upload.name_is_too_long');
        }

        $this->proveritRazmer($command);

        if (!ZipArhivValidator::etoZipArhiv($command->stream)) {
            throw new DomainException('mediateka.upload.file_is_not_zip');
        }

        $id = $this->canGenerateGuid->makeGuid();
        $storageKey = $this->makeStorageKey($unitId, $id);

        $this->fileStorage->save($storageKey, $command->stream, $command->size, FaylUnita::MIME_TYPE_ZIP);

        $file = new FaylUnita(
            $id,
            $unitId,
            $name,
            $command->size,
            FaylUnita::MIME_TYPE_ZIP,
            $storageKey,
            $command->uploadedBy,
            time()
        );

        $this->faylUnitaRepository->save($file);

        return $file;
    }

    private function proveritRazmer(ZagruzitFaylUnita $command): void
    {
        if ($command->contentLength !== null && $command->contentLength > $this->maxFileSize) {
            throw new DomainException('mediateka.upload.file_is_too_large');
        }

        if ($command->size <= 0) {
            throw new DomainException('mediateka.upload.file_is_empty');
        }

        if ($command->size > $this->maxFileSize) {
            throw new DomainException('mediateka.upload.file_is_too_large');
        }
    }

    /**
     * Файл можно загрузить до создания юнита — тогда он становится черновиком
     * и привязывается к юниту позже (см. PrikrepitFaylyKUnituUseCase).
     */
    private function opredelitUnitId(string $unitId): ?string
    {
        $unitId = trim($unitId);

        return $unitId === '' ? null : $unitId;
    }

    private function makeStorageKey(?string $unitId, string $fileId): string
    {
        if ($unitId === null) {
            return sprintf('drafts/%s.zip', $fileId);
        }

        return sprintf('units/%s/%s.zip', $unitId, $fileId);
    }
}
