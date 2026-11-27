<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Port\FaylUnitaRepository;
use App\Mediateka\Business\Port\FileStorage;
use DomainException;

/**
 * Удаление всех файлов workspace.
 *
 * Сначала best-effort удаляются объекты хранилища: ошибка по одному файлу
 * не мешает удалить остальные. Затем удаляются записи реестра.
 * Пустой workspace — не ошибка.
 */
final class UdaliFajlyWorkspaceUseCase
{
    public function __construct(
        private readonly FaylUnitaRepository $faylUnitaRepository,
        private readonly FileStorage $fileStorage,
    ) {
    }

    public function handle(string $workspaceId): void
    {
        if (trim($workspaceId) === '') {
            throw new DomainException('mediateka.file.workspace_id_is_empty');
        }

        $files = $this->faylUnitaRepository->najtiPoWorkspace($workspaceId);

        foreach ($files as $file) {
            try {
                $this->fileStorage->delete($file->getStorageKey());
            } catch (\Throwable) {
                // Удаление объекта best-effort: реестр всё равно чистим.
            }
        }

        $this->faylUnitaRepository->udalitPoWorkspace($workspaceId);
    }
}
