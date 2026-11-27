<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Command\PodgotovitZagruzkuFaylaWorkspace;
use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Model\ZagruzkaFaylaToken;
use App\Mediateka\Business\Port\CanGenerateGuid;
use App\Mediateka\Business\Port\FileStorage;
use App\Mediateka\Business\ReadModel\PodgotovkaZagruzkiFayla;
use App\Mediateka\Business\Utils\DopuskaemyeFaylyWorkspace;
use App\Mediateka\Business\Utils\KlyuchHranilishchaFayla;
use App\Mediateka\Business\Utils\UploadTokenCodec;
use DomainException;

/**
 * Выдаёт presigned PUT URL для прямого файла workspace.
 */
final class PodgotovitZagruzkuFaylaWorkspaceUseCase
{
    public function __construct(
        private readonly FileStorage $fileStorage,
        private readonly CanGenerateGuid $canGenerateGuid,
        private readonly UploadTokenCodec $uploadTokenCodec,
        private readonly DopuskaemyeFaylyWorkspace $validator,
        private readonly int $presignTtl,
    ) {
    }

    public function handle(PodgotovitZagruzkuFaylaWorkspace $command): PodgotovkaZagruzkiFayla
    {
        $name = $this->validator->normalizovatImya($command->name);

        if ($command->size <= 0) {
            throw new DomainException('mediateka.workspace.file_is_empty');
        }

        if ($command->size > $this->validator->maxFileSize()) {
            throw new DomainException('mediateka.workspace.file_is_too_large');
        }

        $contentType = $this->validator->contentType($name);
        $fileId = $this->canGenerateGuid->makeGuid();
        $storageKey = KlyuchHranilishchaFayla::sdelat(null, null, $fileId, null, $name);
        $expiresAt = time() + $this->presignTtl;

        $uploadUrl = $this->fileStorage->presignedPutUrl(
            $storageKey,
            $command->size,
            $contentType,
            $this->presignTtl,
        );

        $uploadToken = $this->uploadTokenCodec->encode(new ZagruzkaFaylaToken(
            $fileId,
            null,
            null,
            $name,
            $command->size,
            $contentType,
            $storageKey,
            $command->uploadedBy,
            $expiresAt,
            FaylUnita::KIND_WORKSPACE,
        ));

        return new PodgotovkaZagruzkiFayla(
            $fileId,
            $storageKey,
            $uploadUrl,
            ['Content-Type' => $contentType],
            $uploadToken,
            $expiresAt,
        );
    }
}
