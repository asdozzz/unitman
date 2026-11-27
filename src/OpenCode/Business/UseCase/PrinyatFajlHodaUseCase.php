<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PrinyatFajlHoda;
use App\OpenCode\Business\Model\StatusHoda;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\ReadModel\FajlHodaReadModel;
use App\OpenCode\Business\Utils\ImyaFajlaHoda;

/**
 * Приём артефакта хода от раннера.
 *
 * Порядок проверок важен для безопасности: сначала подтверждается, что ход
 * существует и ещё выполняется, затем что сессия — workspace и совпадает с
 * `workspaceId` запроса, и только потом проверяются имя, содержимое и лимиты.
 * Так чужой/архивный workspace не может заставить нас читать или создавать
 * файлы.
 *
 * Лимиты проверяются до регистрации. Это осознанный компромисс: повторная
 * загрузка того же `(turnId, name)` при уже достигнутом лимите будет отклонена
 * по количеству, хотя идемпотентная регистрация вернула бы существующий файл.
 * Проверять идемпотентность до лимитов пришлось бы отдельным запросом, а
 * `poluchitStatistikuFajlovHoda` не сообщает имена файлов.
 */
final class PrinyatFajlHodaUseCase
{
    public function __construct(
        private readonly HodOpenCodeRepository $turns,
        private readonly SessiyaOpenCodeRepository $sessions,
        private readonly UmeetUpravlyatFaylamiWorkspace $files,
        private readonly ImyaFajlaHoda $names,
        private readonly int $artifactMaxBytes,
        private readonly int $artifactMaxFiles,
        private readonly int $artifactMaxTotalBytes,
    ) {
    }

    /**
     * @throws \DomainException `opencode.artifact.*`
     */
    public function handle(PrinyatFajlHoda $command): FajlHodaReadModel
    {
        $turn = $this->turns->findByWorkflowId($command->workflowId);

        if ($turn === null) {
            throw new \DomainException('opencode.artifact.turn_not_found');
        }

        if ($turn->getStatus() !== StatusHoda::RUNNING) {
            throw new \DomainException('opencode.artifact.turn_not_running');
        }

        $session = $this->sessions->findById($turn->getSessionId());

        if ($session === null) {
            throw new \DomainException('opencode.artifact.turn_not_found');
        }

        if (!$session->estWorkspace()) {
            throw new \DomainException('opencode.artifact.workspace_required');
        }

        if ($session->getId() !== $command->workspaceId) {
            throw new \DomainException('opencode.artifact.workspace_mismatch');
        }

        if ($session->estArhiv()) {
            throw new \DomainException('opencode.artifact.workspace_archived');
        }

        $name = $this->names->normalizovat($command->name);
        $content = $command->content;

        if ($content === ''
            || str_contains($content, "\0")
            || !mb_check_encoding($content, 'UTF-8')
        ) {
            throw new \DomainException('opencode.artifact.content_invalid');
        }

        $stat = $this->files->poluchitStatistikuFajlovHoda($turn->getId());
        $size = strlen($content);

        if ($stat['count'] >= $this->artifactMaxFiles) {
            throw new \DomainException('opencode.artifact.too_many_files');
        }

        if ($size > $this->artifactMaxBytes) {
            throw new \DomainException('opencode.artifact.file_is_too_large');
        }

        if ($stat['totalSize'] + $size > $this->artifactMaxTotalBytes) {
            throw new \DomainException('opencode.artifact.total_is_too_large');
        }

        return $this->files->zaregistrirovatFajlHoda(
            $session->getId(),
            $session->getAccountId(),
            $turn->getId(),
            $name,
            $command->contentType,
            $content,
            time(),
        );
    }
}
