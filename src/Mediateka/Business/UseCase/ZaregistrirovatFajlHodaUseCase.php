<?php

declare(strict_types=1);

namespace App\Mediateka\Business\UseCase;

use App\Mediateka\Business\Model\FaylUnita;
use App\Mediateka\Business\Port\CanGenerateGuid;
use App\Mediateka\Business\Port\FaylUnitaRepository;
use App\Mediateka\Business\Port\FileStorage;
use App\Mediateka\Business\Utils\DopuskaemyeFajlyHoda;
use App\Mediateka\Business\Utils\KlyuchHranilishchaFayla;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use DomainException;

/**
 * Регистрирует артефакт, созданный агентом в ходе OpenCode.
 *
 * Файл принадлежит workspace, поэтому лежит в объектах workspace и удаляется
 * вместе с ним. Повторная регистрация того же `(turnId, name)` идемпотентна:
 * возвращается уже существующая запись.
 */
final class ZaregistrirovatFajlHodaUseCase
{
    public function __construct(
        private readonly FaylUnitaRepository $faylUnitaRepository,
        private readonly FileStorage $fileStorage,
        private readonly CanGenerateGuid $canGenerateGuid,
        private readonly DopuskaemyeFajlyHoda $validator,
    ) {
    }

    public function handle(
        string $workspaceId,
        string $accountId,
        string $turnId,
        string $name,
        string $contentType,
        string $content,
        int $now,
    ): FaylUnita {
        if (trim($workspaceId) === '') {
            throw new DomainException('mediateka.file.workspace_id_is_empty');
        }
        if (trim($turnId) === '') {
            throw new DomainException('mediateka.file.opencode_turn_id_is_empty');
        }

        $normalizedName = $this->validator->normalizovatImya($name);
        $this->validator->proveritSoderzhimoe($content);

        $size = strlen($content);

        if ($size > $this->validator->maxFileSize()) {
            throw new DomainException('mediateka.workspace.file_is_too_large');
        }

        $existing = $this->faylUnitaRepository->najtiPoHoduImImeni($turnId, $normalizedName);
        if ($existing !== null) {
            return $existing;
        }

        $canonicalContentType = $this->validator->contentType($normalizedName);
        $fileId = $this->canGenerateGuid->makeGuid();
        $storageKey = KlyuchHranilishchaFayla::sdelat(null, null, $fileId, $workspaceId, $normalizedName);

        $stream = fopen('php://temp', 'rb+');
        if ($stream === false) {
            throw new \RuntimeException('Не удалось открыть временный поток для файла хода');
        }

        try {
            fwrite($stream, $content);
            rewind($stream);

            $this->fileStorage->save($storageKey, $stream, $size, $canonicalContentType);
        } finally {
            fclose($stream);
        }

        $file = new FaylUnita(
            $fileId,
            null,
            $normalizedName,
            $size,
            $canonicalContentType,
            $storageKey,
            $accountId,
            $now,
            null,
            null,
            FaylUnita::KIND_WORKSPACE,
            null,
            null,
        );

        $file = $file->prikrepitKWorkspaceKakFajlHoda($workspaceId, $turnId, $now);

        try {
            $this->faylUnitaRepository->save($file);
        } catch (UniqueConstraintViolationException $e) {
            $raced = $this->faylUnitaRepository->najtiPoHoduImImeni($turnId, $normalizedName);
            if ($raced !== null) {
                return $raced;
            }

            throw $e;
        }

        return $file;
    }
}
