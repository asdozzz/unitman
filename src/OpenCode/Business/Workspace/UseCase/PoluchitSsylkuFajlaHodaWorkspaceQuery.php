<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\PoluchitSsylkuFajlaHoda;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\ReadModel\FajlHodaReadModel;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

/**
 * Подписанная ссылка на файл-артефакт хода на самостоятельном агрегате.
 */
final class PoluchitSsylkuFajlaHodaWorkspaceQuery
{
    public function __construct(
        private readonly WorkspaceTurnRepository $turns,
        private readonly WorkspaceSessionRepository $sessions,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatFaylamiWorkspace $files,
        private readonly ProverkaDostupaWorkspaceSessii $dostup,
    ) {
    }

    /**
     * @return array{fileId: string, name: string, contentType: string, size: int, url: string}
     */
    public function handle(PoluchitSsylkuFajlaHoda $command): array
    {
        $file = $this->trebovatFajl($command->turnId, $command->fileId);

        $url = $this->files->poluchitSsylkuDlyaFajlaHoda($file->turnId, $file->fileId);

        if ($url === null) {
            throw new \DomainException('opencode.artifact.not_found');
        }

        return [
            'fileId' => $file->fileId,
            'name' => $file->name,
            'contentType' => $file->contentType,
            'size' => $file->size,
            'url' => $url,
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
