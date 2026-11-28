<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitResultatHoda;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\ReadModel\HodOpenCodeReadModel;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Business\Utils\ZavershenieHodaOpenCode;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\UseCase\PoluchitResultatHodaWorkspaceQuery;

/**
 * Опрос результата хода — единственный способ узнать, чем закончилась работа.
 *
 * Пока ход в статусе RUNNING, результата у раннера может не быть: тогда
 * отдаётся сохранённое состояние, и клиент опрашивает снова. Как только
 * результат получен, завершение выполняется общей точкой
 * `ZavershenieHodaOpenCode`: она фиксирует результат, освобождает юниты и
 * создаёт уведомление. Повторный опрос в терминальном статусе ничего не пишет.
 */
final class PoluchitResultatHodaQuery
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private HodOpenCodeRepository $turnRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetVypolnyatHodOpenCode $runner,
        private ZavershenieHodaOpenCode $zavershenie,
        private ProverkaDostupaKSessii $dostup,
        private ?WorkspaceFeatureFlags $flags = null,
        private ?PoluchitResultatHodaWorkspaceQuery $workspaceQuery = null,
    ) {
    }

    /**
     * @throws \DomainException если ход не найден или принадлежит чужой сессии
     */
    public function handle(PoluchitResultatHoda $command): HodOpenCodeReadModel
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        if ($this->flags?->chitatIzNovogoAgregata() === true && $this->workspaceQuery !== null) {
            $legacyTurn = $this->turnRepository->findById($command->turnId);
            $legacySession = $legacyTurn !== null
                ? $this->sessionRepository->findById($legacyTurn->getSessionId())
                : null;

            if ($legacyTurn === null || $legacySession?->estWorkspace() === true) {
                return $this->workspaceQuery->handle($command->turnId);
            }
        }

        $turn = $this->turnRepository->getById($command->turnId);
        $session = $this->sessionRepository->getById($turn->getSessionId());

        $this->dostup->trebovat($session, $accountId, RolUchastnikaSessii::VIEWER);

        $turn = $this->zavershenie->zavershit($turn);

        return HodOpenCodeReadModel::izModela(
            $turn,
            $this->runner->kanalProgressa($turn->getWorkflowId()),
        );
    }
}
