<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Command\ZavershitZagruzkuFayla;
use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\FaylUnitaRepository;
use App\Mediateka\Business\Port\FileStorage;
use App\Mediateka\Business\Utils\DopuskaemyeFaylyWorkspace;
use App\Mediateka\Business\Utils\UploadTokenCodec;
use DomainException;
use Throwable;

/**
 * Завершает прямую загрузку: проверяет подпись и срок токена, сверяет
 * фактический размер объекта в хранилище с заявленным и сохраняет реестр.
 *
 * Для файлов workspace дополнительно проверяется содержимое: сигнатура
 * изображения или UTF-8-текст без NUL. Невалидный объект удаляется.
 */
final class ZavershitZagruzkuFaylaUseCase
{
    public function __construct(
        private readonly FaylUnitaRepository $faylUnitaRepository,
        private readonly FileStorage $fileStorage,
        private readonly UploadTokenCodec $uploadTokenCodec,
        private readonly ?DopuskaemyeFaylyWorkspace $workspaceValidator = null,
    ) {
    }

    public function handle(ZavershitZagruzkuFayla $command): FaylUnita
    {
        $token = $this->uploadTokenCodec->decode($command->uploadToken);

        if ($token->expiresAt < time()) {
            throw new DomainException('mediateka.upload.token_expired');
        }

        $object = $this->fileStorage->stat($token->storageKey);
        if ($object === null) {
            throw new DomainException('mediateka.upload.file_is_missing');
        }

        if ($object['size'] !== $token->size) {
            throw new DomainException('mediateka.upload.size_mismatch');
        }

        $kind = $token->kind ?? FaylUnita::KIND_DRAFT;

        if ($kind === FaylUnita::KIND_WORKSPACE) {
            $this->proveritFaylWorkspace($token);
        }

        $file = new FaylUnita(
            $token->fileId,
            $token->unitId,
            $token->name,
            $token->size,
            $token->contentType,
            $token->storageKey,
            $token->uploadedBy,
            time(),
            $token->projectId,
            null,
            $kind,
        );

        $this->faylUnitaRepository->save($file);

        return $file;
    }

    private function proveritFaylWorkspace(\App\Mediateka\Business\Model\ZagruzkaFaylaToken $token): void
    {
        if ($this->workspaceValidator === null) {
            throw new \RuntimeException('Валидатор файлов workspace не настроен');
        }

        $tempPath = null;

        try {
            $tempPath = $this->fileStorage->downloadToTemp(
                $token->storageKey,
                $this->workspaceValidator->maxFileSize(),
            );

            $this->workspaceValidator->proveritFayl($tempPath, $token->name, $token->size);
        } catch (Throwable $error) {
            $this->fileStorage->delete($token->storageKey);

            throw $error;
        } finally {
            if ($tempPath !== null) {
                @unlink($tempPath);
            }
        }
    }
}
