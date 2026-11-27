<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitTekstFajlaHoda;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\ReadModel\FajlHodaReadModel;

/**
 * Текст файла-артефакта хода для preview.
 *
 * Владелец и принадлежность файла проверяются так же, как для ссылки. Затем
 * тип обязан быть previewable, размер — не больше `OPENCODE_ARTIFACT_PREVIEW_MAX_BYTES`,
 * а содержимое — валидный UTF-8 (проверяется Mediateka при чтении).
 */
final class PoluchitTekstFajlaHodaQuery
{
    public function __construct(
        private readonly HodOpenCodeRepository $turns,
        private readonly SessiyaOpenCodeRepository $sessions,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatFaylamiWorkspace $files,
        private readonly int $artifactPreviewMaxBytes,
    ) {
    }

    /**
     * @return array{fileId: string, name: string, contentType: string, content: string}
     *
     * @throws \DomainException `opencode.turn.not_found`, `opencode.session.not_found`,
     *                          `opencode.artifact.not_found`, `opencode.artifact.preview_unsupported`,
     *                          `opencode.artifact.preview_too_large`, `opencode.artifact.content_invalid`
     */
    public function handle(PoluchitTekstFajlaHoda $command): array
    {
        $file = $this->trebovatFajl($command->turnId, $command->fileId);

        if (!$file->podderzhivaetProsmotr()) {
            throw new \DomainException('opencode.artifact.preview_unsupported');
        }

        if ($file->size > $this->artifactPreviewMaxBytes) {
            throw new \DomainException('opencode.artifact.preview_too_large');
        }

        try {
            $content = $this->files->poluchitSoderzhimoeFajla($file->fileId, $this->artifactPreviewMaxBytes);
        } catch (\DomainException $exception) {
            throw match ($exception->getMessage()) {
                'mediateka.file.content_is_invalid' => new \DomainException('opencode.artifact.content_invalid', 0, $exception),
                'mediateka.file.too_large' => new \DomainException('opencode.artifact.preview_too_large', 0, $exception),
                default => $exception,
            };
        }

        return [
            'fileId' => $file->fileId,
            'name' => $file->name,
            'contentType' => $file->contentType,
            'content' => $content,
        ];
    }

    /**
     * @throws \DomainException
     */
    private function trebovatFajl(string $turnId, string $fileId): FajlHodaReadModel
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        $turn = $this->turns->findById(trim($turnId));

        if ($turn === null) {
            throw new \DomainException('opencode.turn.not_found');
        }

        $session = $this->sessions->findById($turn->getSessionId());

        if ($session === null) {
            throw new \DomainException('opencode.session.not_found');
        }

        $session->trebovatVladelca($accountId);

        if (!$session->estWorkspace()) {
            throw new \DomainException('opencode.artifact.not_found');
        }

        $file = $this->files->poluchitFajlHoda($turn->getId(), trim($fileId));

        if ($file === null
            || $file->workspaceId !== $session->getId()
            || $file->turnId !== $turn->getId()
        ) {
            throw new \DomainException('opencode.artifact.not_found');
        }

        return $file;
    }
}
