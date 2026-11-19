<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Command\ZavershitZagruzkuFayla;
use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\FaylUnitaRepository;
use App\Mediateka\Business\Port\FileStorage;
use App\Mediateka\Business\Utils\UploadTokenCodec;
use DomainException;

/**
 * Завершает прямую загрузку: проверяет подпись и срок токена, сверяет
 * фактический размер объекта в хранилище с заявленным и сохраняет реестр.
 */
final class ZavershitZagruzkuFaylaUseCase
{
    public function __construct(
        private readonly FaylUnitaRepository $faylUnitaRepository,
        private readonly FileStorage $fileStorage,
        private readonly UploadTokenCodec $uploadTokenCodec
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

        $file = new FaylUnita(
            $token->fileId,
            $token->unitId,
            $token->name,
            $token->size,
            $token->contentType,
            $token->storageKey,
            $token->uploadedBy,
            time(),
            $token->projectId
        );

        $this->faylUnitaRepository->save($file);

        return $file;
    }
}
