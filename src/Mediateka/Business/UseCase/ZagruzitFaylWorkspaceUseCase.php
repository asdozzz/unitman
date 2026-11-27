<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Command\ZagruzitFaylWorkspace;
use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\CanGenerateGuid;
use App\Mediateka\Business\Port\FaylUnitaRepository;
use App\Mediateka\Business\Port\FileStorage;
use App\Mediateka\Business\Utils\DopuskaemyeFaylyWorkspace;
use App\Mediateka\Business\Utils\KlyuchHranilishchaFayla;
use DomainException;

/**
 * Потоковая загрузка одного файла workspace.
 *
 * Файл сохраняется как черновик с `kind=workspace` и без workspace_id:
 * привязка к workspace выполняется OpenCode после проверки владельца.
 */
final class ZagruzitFaylWorkspaceUseCase
{
    public const CONTENT_LENGTH_ALLOWANCE = 1048576;

    public function __construct(
        private readonly FaylUnitaRepository $faylUnitaRepository,
        private readonly FileStorage $fileStorage,
        private readonly CanGenerateGuid $canGenerateGuid,
        private readonly DopuskaemyeFaylyWorkspace $validator,
    ) {
    }

    public function handle(ZagruzitFaylWorkspace $command): FaylUnita
    {
        $name = $this->validator->normalizovatImya($command->name);

        if (
            $command->contentLength !== null
            && $command->contentLength > $this->validator->maxFileSize() + self::CONTENT_LENGTH_ALLOWANCE
        ) {
            throw new DomainException('mediateka.workspace.file_is_too_large');
        }

        if ($command->size <= 0) {
            throw new DomainException('mediateka.workspace.file_is_empty');
        }

        if ($command->size > $this->validator->maxFileSize()) {
            throw new DomainException('mediateka.workspace.file_is_too_large');
        }

        $source = $command->stream;
        if (!is_resource($source)) {
            throw new DomainException('mediateka.upload.file_is_unreadable');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'mediateka_ws_');
        if ($tempPath === false) {
            throw new \RuntimeException('Не удалось создать временный файл для проверки');
        }

        $target = fopen($tempPath, 'wb');
        if ($target === false) {
            @unlink($tempPath);
            throw new \RuntimeException('Не удалось открыть временный файл для проверки');
        }

        try {
            stream_copy_to_stream($source, $target);
            fclose($target);

            $actualSize = filesize($tempPath);
            if ($actualSize === false || $actualSize !== $command->size) {
                throw new DomainException('mediateka.upload.size_mismatch');
            }

            $this->validator->proveritFayl($tempPath, $name, $command->size);

            $fileId = $this->canGenerateGuid->makeGuid();
            $contentType = $this->validator->contentType($name);
            $storageKey = KlyuchHranilishchaFayla::sdelat(null, null, $fileId, null, $name);

            $streamToSave = fopen($tempPath, 'rb');
            if ($streamToSave === false) {
                throw new \RuntimeException('Не удалось перечитать временный файл');
            }

            try {
                $this->fileStorage->save($storageKey, $streamToSave, $command->size, $contentType);
            } finally {
                fclose($streamToSave);
            }

            $file = new FaylUnita(
                $fileId,
                null,
                $name,
                $command->size,
                $contentType,
                $storageKey,
                $command->uploadedBy,
                time(),
                null,
                null,
                FaylUnita::KIND_WORKSPACE,
                null,
            );

            $this->faylUnitaRepository->save($file);

            return $file;
        } finally {
            @unlink($tempPath);
        }
    }
}
