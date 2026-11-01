<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Command\PrikrepitFaylyKUnitu;
use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\FaylUnitaRepository;
use DomainException;

/**
 * Привязывает черновики файлов к юниту.
 *
 * Нужно для формы создания юнита: файлы загружаются до того, как юнит создан,
 * и потому сохраняются без unit_id. После создания юнита этот сценарий
 * проставляет им unit_id. Ключ в хранилище при этом не меняется — файл
 * не копируется в MinIO.
 */
final class PrikrepitFaylyKUnituUseCase
{
    public function __construct(private readonly FaylUnitaRepository $faylUnitaRepository)
    {
    }

    /**
     * @return FaylUnita[] привязанные файлы
     */
    public function handle(PrikrepitFaylyKUnitu $command): array
    {
        $unitId = trim($command->unitId);
        if ($unitId === '') {
            throw new DomainException('mediateka.bind.unit_id_is_empty');
        }

        $fileIds = $this->normalizovatFileIds($command->fileIds);
        if ($fileIds === []) {
            return [];
        }

        $privyazannie = [];

        foreach ($fileIds as $fileId) {
            $file = $this->faylUnitaRepository->findById($fileId);
            if ($file === null) {
                throw new DomainException('mediateka.bind.file_not_found');
            }

            if ($file->getUnitId() === $unitId) {
                $privyazannie[$file->getId()] = $file;

                continue;
            }

            if (!$file->esliChernovik()) {
                throw new DomainException('mediateka.bind.file_belongs_to_another_unit');
            }

            $this->faylUnitaRepository->prikrepitKUnitu($file->getId(), $unitId);
            $privyazannie[$file->getId()] = $file->prikrepitKUnitu($unitId);
        }

        return array_values($privyazannie);
    }

    /**
     * @param mixed $fileIds
     *
     * @return string[]
     */
    private function normalizovatFileIds(mixed $fileIds): array
    {
        if (!is_array($fileIds)) {
            throw new DomainException('mediateka.bind.file_ids_format_invalid');
        }

        $rezultat = [];

        foreach ($fileIds as $fileId) {
            if (!is_string($fileId)) {
                throw new DomainException('mediateka.bind.file_ids_format_invalid');
            }

            $fileId = trim($fileId);
            if ($fileId === '') {
                throw new DomainException('mediateka.bind.file_id_is_empty');
            }

            $rezultat[$fileId] = $fileId;
        }

        return array_values($rezultat);
    }
}
