<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSsylkuFajlaHoda;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\UseCase\PoluchitSsylkuFajlaHodaWorkspaceQuery;

/**
 * Подписанная ссылка на файл-артефакт хода.
 *
 * Доступ проверяется по участию в сессии (viewer и выше), файл — по паре
 * `(turnId, fileId)` и по совпадению workspace хода. Чужой/несуществующий ход
 * и файл дают одинаковый `opencode.artifact.not_found`: не раскрываем
 * существование.
 */
final class PoluchitSsylkuFajlaHodaQuery
{
    public function __construct(
        private readonly HodOpenCodeRepository $turns,
        private readonly SessiyaOpenCodeRepository $sessions,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatFaylamiWorkspace $files,
        private readonly ProverkaDostupaKSessii $dostup,
        private readonly ?WorkspaceFeatureFlags $flags = null,
        private readonly ?PoluchitSsylkuFajlaHodaWorkspaceQuery $workspaceQuery = null,
    ) {
    }

    /**
     * @return array{fileId: string, name: string, contentType: string, size: int, url: string}
     *
     * @throws \DomainException `opencode.turn.not_found`, `opencode.session.not_found`,
     *                          `opencode.artifact.not_found`
     */
    public function handle(PoluchitSsylkuFajlaHoda $command): array
    {
        if ($this->flags?->chitatIzNovogoAgregata() === true && $this->workspaceQuery !== null) {
            $legacyTurn = $this->turns->findById(trim($command->turnId));
            $legacySession = $legacyTurn !== null
                ? $this->sessions->findById($legacyTurn->getSessionId())
                : null;

            if ($legacyTurn === null || $legacySession?->estWorkspace() === true) {
                return $this->workspaceQuery->handle($command);
            }
        }

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

    /**
     * Общая последовательность доступа для ссылки и текста.
     *
     * @throws \DomainException
     */
    private function trebovatFajl(string $turnId, string $fileId): \App\OpenCode\Business\ReadModel\FajlHodaReadModel
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

        // Не-участник -> opencode.session.not_found (не раскрываем сессию).
        $this->dostup->trebovat($session, $accountId, RolUchastnikaSessii::VIEWER);

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
