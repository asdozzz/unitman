<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Command\PodgotovitZagruzkuFayla;
use App\Mediateka\Business\Model\ZagruzkaFaylaToken;
use App\Mediateka\Business\Port\CanGenerateGuid;
use App\Mediateka\Business\Port\FileStorage;
use App\Mediateka\Business\ReadModel\PodgotovkaZagruzkiFayla;
use App\Mediateka\Business\Utils\ImyaFaylaDlyaDockerVolume;
use App\Mediateka\Business\Utils\KlyuchHranilishchaFayla;
use App\Mediateka\Business\Utils\UploadTokenCodec;
use DomainException;

/**
 * Проверяет параметры будущего файла и выдаёт presigned PUT URL вместе
 * с подписанным токеном для последующего finalize.
 */
final class PodgotovitZagruzkuFaylaUseCase
{
    private const ZIP_CONTENT_TYPE = 'application/zip';

    public function __construct(
        private readonly FileStorage $fileStorage,
        private readonly CanGenerateGuid $canGenerateGuid,
        private readonly UploadTokenCodec $uploadTokenCodec,
        private readonly int $maxFileSize,
        private readonly int $presignTtl
    ) {
    }

    public function handle(PodgotovitZagruzkuFayla $command): PodgotovkaZagruzkiFayla
    {
        $unitId = $this->normalizovatId($command->unitId);
        $projectId = $this->normalizovatId($command->projectId);

        if ($unitId !== null && $projectId !== null) {
            throw new DomainException('mediateka.upload.owner_is_ambiguous');
        }

        $name = $this->normalizovatImya($command->name);

        if ($command->size <= 0) {
            throw new DomainException('mediateka.upload.file_is_empty');
        }

        if ($command->size > $this->maxFileSize) {
            throw new DomainException('mediateka.upload.file_is_too_large');
        }

        $contentType = $this->normalizovatContentType($command->contentType);
        if ($contentType !== self::ZIP_CONTENT_TYPE) {
            throw new DomainException('mediateka.upload.file_is_not_zip');
        }

        if ($this->presignTtl <= 0) {
            throw new \RuntimeException('mediateka.upload.presign_ttl_invalid');
        }

        $fileId = $this->canGenerateGuid->makeGuid();
        $storageKey = KlyuchHranilishchaFayla::sdelat($unitId, $projectId, $fileId);
        $expiresAt = time() + $this->presignTtl;

        $uploadUrl = $this->fileStorage->presignedPutUrl(
            $storageKey,
            $command->size,
            $contentType,
            $this->presignTtl
        );

        $uploadToken = $this->uploadTokenCodec->encode(new ZagruzkaFaylaToken(
            $fileId,
            $unitId,
            $projectId,
            $name,
            $command->size,
            $contentType,
            $storageKey,
            $command->uploadedBy,
            $expiresAt
        ));

        return new PodgotovkaZagruzkiFayla(
            $fileId,
            $storageKey,
            $uploadUrl,
            ['Content-Type' => $contentType],
            $uploadToken,
            $expiresAt
        );
    }

    private function normalizovatImya(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new DomainException('mediateka.upload.name_is_empty');
        }

        if (mb_strlen($name) > ZagruzitFaylUnitaUseCase::NAME_MAX_LENGTH) {
            throw new DomainException('mediateka.upload.name_is_too_long');
        }

        return ImyaFaylaDlyaDockerVolume::normalizovatImyaArhiva($name);
    }

    private function normalizovatContentType(string $contentType): string
    {
        $contentType = strtolower(trim(explode(';', $contentType, 2)[0]));

        return $contentType === '' ? self::ZIP_CONTENT_TYPE : $contentType;
    }

    private function normalizovatId(string $id): ?string
    {
        $id = trim($id);

        return $id === '' ? null : $id;
    }
}
