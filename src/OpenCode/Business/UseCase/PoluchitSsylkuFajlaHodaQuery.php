<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSsylkuFajlaHoda;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;

/**
 * Подписанная ссылка на файл-артефакт хода.
 *
 * Владелец проверяется по текущему аккаунту, файл — по паре `(turnId, fileId)`
 * и по совпадению workspace хода. Чужой/несуществующий ход и файл дают
 * одинаковый `opencode.artifact.not_found`: не раскрываем существование.
 */
final class PoluchitSsylkuFajlaHodaQuery
{
    public function __construct(
        private readonly HodOpenCodeRepository $turns,
        private readonly SessiyaOpenCodeRepository $sessions,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatFaylamiWorkspace $files,
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
     * Общая последовательность владельца для ссылки и текста.
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

        // Чужой владелец -> opencode.session.not_found (не раскрываем сессию).
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
