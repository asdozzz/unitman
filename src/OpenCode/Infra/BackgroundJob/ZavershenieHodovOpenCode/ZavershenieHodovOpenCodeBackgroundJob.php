<?php

declare(strict_types=1);

namespace App\OpenCode\Infra\BackgroundJob\ZavershenieHodovOpenCode;

use App\BackgroundJob\Infra\Service\AbstractBackgroundJob;
use App\BackgroundJob\Infra\Service\BackgroundJobGroup;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Utils\ZavershenieHodaOpenCode;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ZavershenieHodaWorkspace;
use Psr\Log\LoggerInterface;

/**
 * Watchdog завершения ходов OpenCode.
 *
 * Периодически выбирает ходы в RUNNING и пробует получить их результат у
 * раннера. Ошибка одного хода логируется и не прерывает обработку остальных:
 * следующий тик повторит попытку. Инфраструктурная ошибка выборки также не
 * пробрасывается наружу — планировщик не должен считать задачу упавшей.
 */
final class ZavershenieHodovOpenCodeBackgroundJob extends AbstractBackgroundJob
{
    private const BATCH_LIMIT = 10;

    public function __construct(
        private HodOpenCodeRepository $turnRepository,
        private ZavershenieHodaOpenCode $zavershenie,
        private LoggerInterface $logger,
        private ?WorkspaceTurnRepository $workspaceTurns = null,
        private ?ZavershenieHodaWorkspace $workspaceZavershenie = null,
    ) {
    }

    public function getName(): string
    {
        return 'opencode_turn_watchdog';
    }

    public function getGroup(): BackgroundJobGroup
    {
        return BackgroundJobGroup::timers;
    }

    public function getDelay(): int
    {
        return 5;
    }

    public function getProcessNum(): int
    {
        return 1;
    }

    public function run(): bool
    {
        $legacyIds = [];

        try {
            $turns = $this->turnRepository->findVProzesse(self::BATCH_LIMIT);
        } catch (\Throwable $error) {
            $this->logger->error('opencode.turn_watchdog.batch_load_failed', [
                'exception' => $error,
            ]);

            $turns = [];
        }

        foreach ($turns as $turn) {
            $legacyIds[$turn->getId()] = true;
            $this->zavershit($turn);
        }

        // In-flight ходы, созданные уже на новом агрегате, завершаются его
        // репозиторием. Дедупликация по id: зеркало держит один и тот же ход в
        // двух таблицах, повторное завершение недопустимо.
        if ($this->workspaceTurns === null || $this->workspaceZavershenie === null) {
            return true;
        }

        try {
            $workspaceTurns = $this->workspaceTurns->findVProzesse(self::BATCH_LIMIT);
        } catch (\Throwable $error) {
            $this->logger->error('opencode.turn_watchdog.workspace_batch_load_failed', [
                'exception' => $error,
            ]);

            return true;
        }

        foreach ($workspaceTurns as $turn) {
            if (isset($legacyIds[$turn->getId()])) {
                continue;
            }

            $this->zavershitVWorkspace($turn);
        }

        return true;
    }

    private function zavershit(HodOpenCode $turn): void
    {
        try {
            $this->zavershenie->zavershit($turn);
        } catch (\Throwable $error) {
            // Один проблемный ход не должен блокировать остальные: следующий
            // тик снова попробует его завершить.
            $this->logger->error('opencode.turn_watchdog.turn_failed', [
                'turnId' => $turn->getId(),
                'exception' => $error,
            ]);
        }
    }

    private function zavershitVWorkspace(HodOpenCode $turn): void
    {
        try {
            $this->workspaceZavershenie?->zavershit($turn);
        } catch (\Throwable $error) {
            $this->logger->error('opencode.turn_watchdog.workspace_turn_failed', [
                'turnId' => $turn->getId(),
                'exception' => $error,
            ]);
        }
    }
}
