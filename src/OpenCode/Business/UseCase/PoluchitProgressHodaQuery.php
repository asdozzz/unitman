<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitProgressHoda;
use App\OpenCode\Business\Model\ProgressHodaOpenCode;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\UseCase\PoluchitProgressHodaWorkspaceQuery;

/**
 * Pull-опрос шагов, которые раннер уже прислал через saveSteps.
 *
 * Push-доставка идёт отдельным каналом Centrifugo, этот запрос нужен как
 * резервный путь: восстановить состояние после перезагрузки страницы или
 * если подписка на канал не установилась.
 */
final class PoluchitProgressHodaQuery
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private HodOpenCodeRepository $turnRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetVypolnyatHodOpenCode $runner,
        private ProverkaDostupaKSessii $dostup,
        private ?WorkspaceFeatureFlags $flags = null,
        private ?PoluchitProgressHodaWorkspaceQuery $workspaceQuery = null,
    ) {
    }

    /**
     * @throws \DomainException если ход не найден или принадлежит чужой сессии
     */
    public function handle(PoluchitProgressHoda $command): ?ProgressHodaOpenCode
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

        return $this->runner->poluchitProgressHoda($turn->getWorkflowId());
    }
}
