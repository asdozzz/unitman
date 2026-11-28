<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\AgentMode\Utils\OpredelitelRezhimaAgentaHoda;
use App\OpenCode\Business\Command\NachatPrompt;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\ReadModel\RezultatZapuskaHoda;
use App\OpenCode\Business\Utils\OpredelitelRekvizitovHodaOpenCode;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\UseCase\NachatPromptWorkspaceUseCase;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Business\Utils\PropuskHodaOpenCode;
use App\OpenCode\Business\Utils\ZapuskatelHodaOpenCode;

/**
 * Новый промпт в существующей сессии.
 *
 * Промпт отклоняется, если предыдущий ход ждёт ответа на вопрос: продолжение
 * диалога в этом случае возможно только через [`OtvetitNaVoprosUseCase`](src/OpenCode/Business/UseCase/OtvetitNaVoprosUseCase.php).
 */
final class NachatPromptUseCase
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private HodOpenCodeRepository $turnRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private ZapuskatelHodaOpenCode $launcher,
        private UmeetVypolnyatHodOpenCode $runner,
        private PropuskHodaOpenCode $propusk,
        private ProverkaDostupaKSessii $dostup,
        private OpredelitelRekvizitovHodaOpenCode $rekvizity,
        private OpredelitelRezhimaAgentaHoda $rezhimyAgentov,
        private ?WorkspaceFeatureFlags $flags = null,
        private ?NachatPromptWorkspaceUseCase $workspaceUseCase = null,
    ) {
    }

    public function handle(NachatPrompt $command): RezultatZapuskaHoda
    {
        $accountId = $this->credentials->tekuschiiAccountId();

        $session = $this->sessionRepository->getById($command->sessionId);

        // Unit-сессии новый агрегат не затрагивает ни при каких флагах.
        if ($session->estWorkspace()
            && $this->flags?->pisatVNoviyAgregat() === true
            && $this->workspaceUseCase !== null
        ) {
            return $this->workspaceUseCase->handle(
                $session->getId(),
                $command->text,
                $command->model,
                $command->credentialId,
                $command->agentModeId,
            );
        }

        $uchastnik = $this->dostup->trebovat($session, $accountId, RolUchastnikaSessii::WRITER);
        $units = $this->propusk->proverit($session);

        $poslednii = $this->turnRepository->findPosledniiPoSessii($session->getId());

        if ($poslednii !== null && $poslednii->zhdetOtveta()) {
            throw new \DomainException('opencode.turn.awaiting_answer');
        }

        $rezhimAgenta = $this->rezhimyAgentov->opredelit(
            $accountId,
            OpredelitelRezhimaAgentaHoda::CHAT_UNIT,
            $command->agentModeId,
        );

        $istochnik = $this->rekvizity->opredelit(
            $session,
            $accountId,
            $uchastnik,
            $command->credentialId ?? $rezhimAgenta->credentialId,
        );

        $turn = $this->launcher->zapustit(
            $session,
            $command->text,
            [],
            $units,
            $command->model,
            $istochnik,
            $rezhimAgenta,
        );

        return new RezultatZapuskaHoda(
            $turn->getId(),
            $turn->getStatus()->value,
            $this->runner->kanalProgressa($turn->getWorkflowId()),
        );
    }
}