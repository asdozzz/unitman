<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Command\PrikrepitFaylyKWorkspace;
use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\FaylUnitaRepository;
use DomainException;

/**
 * Привязывает черновики файлов workspace к workspace.
 *
 * Ключ в хранилище не меняется. Файл, уже привязанный к другому workspace,
 * не принимается. Ограничения по количеству проверяет вызывающая сторона.
 */
final class PrikrepitFaylyKWorkspaceUseCase
{
    public function __construct(private readonly FaylUnitaRepository $faylUnitaRepository)
    {
    }

    /**
     * @return FaylUnita[]
     */
    public function handle(PrikrepitFaylyKWorkspace $command): array
    {
        $workspaceId = trim($command->workspaceId);

        if ($workspaceId === '') {
            throw new DomainException('mediateka.file.workspace_id_is_empty');
        }

        $fileIds = array_values(array_unique(array_filter(
            array_map(static fn (string $id): string => trim($id), $command->fileIds),
            static fn (string $id): bool => $id !== '',
        )));

        if ($fileIds === []) {
            throw new DomainException('mediateka.bind.file_ids_format_invalid');
        }

        $files = [];

        foreach ($fileIds as $fileId) {
            $file = $this->faylUnitaRepository->findById($fileId);

            if ($file === null) {
                throw new DomainException('mediateka.file.not_found');
            }

            if ($file->getKind() !== FaylUnita::KIND_WORKSPACE) {
                throw new DomainException('mediateka.file.is_not_workspace_draft');
            }

            if (!$file->esliChernovik() && $file->getWorkspaceId() !== $workspaceId) {
                throw new DomainException('mediateka.file.is_not_workspace_draft');
            }

            if ($file->esliChernovik()) {
                $this->faylUnitaRepository->prikrepitKWorkspace($fileId, $workspaceId);
            }

            $updated = $this->faylUnitaRepository->findById($fileId);
            if ($updated !== null) {
                $files[] = $updated;
            }
        }

        return $files;
    }
}
