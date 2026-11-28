<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\PoluchitTekstFajlaHoda;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\ReadModel\FajlHodaReadModel;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

/**
 * Текст файла-артефакта хода на самостоятельном агрегате.
 */
final class PoluchitTekstFajlaHodaWorkspaceQuery
{
    public function __construct(
        private readonly WorkspaceTurnRepository $turns,
        private readonly WorkspaceSessionRepository $sessions,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatFaylamiWorkspace $files,
        private readonly ProverkaDostupaWorkspaceSessii $dostup,
        private readonly int $artifactPreviewMaxBytes,
    ) {
    }

    /**
     * @return array{fileId: string, name: string, contentType: string, content: string}
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

    private function trebovatFajl(string $turnId, string $fileId): FajlHodaReadModel
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        $turn = $this->turns->findById(trim($turnId));

        if ($turn === null) {
            throw new \DomainException('opencode.turn.not_found');
        }

        $workspace = $this->sessions->findById($turn->getSessionId());

        if ($workspace === null) {
            throw new \DomainException('opencode.session.not_found');
        }

        $this->dostup->trebovat($workspace, $accountId, RolUchastnikaSessii::VIEWER);

        $file = $this->files->poluchitFajlHoda($turn->getId(), trim($fileId));

        if ($file === null
            || $file->workspaceId !== $workspace->getId()
            || $file->turnId !== $turn->getId()
        ) {
            throw new \DomainException('opencode.artifact.not_found');
        }

        return $file;
    }
}
