<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\AgentMode\Utils\OpredelitelRezhimaAgentaHoda;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\ReadModel\RezultatZapuskaHoda;
use App\OpenCode\Business\Utils\OpredelitelRekvizitovHodaOpenCode;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;
use App\OpenCode\Business\Workspace\Utils\ZapuskatelHodaWorkspace;

/**
 * Новый промпт в workspace на самостоятельном агрегате.
 *
 * Реквизиты резолвятся по правилам соавторства: запрос -> default участника ->
 * (только владельцу) реквизиты сессии -> первые реквизиты аккаунта. Ход
 * стартует через `ZapuskatelHodaWorkspace`, который ставит резервацию и
 * проверяет доступ автора к проектам.
 */
final class NachatPromptWorkspaceUseCase
{
    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly WorkspaceTurnRepository $turns,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetVypolnyatHodOpenCode $runner,
        private readonly ProverkaDostupaWorkspaceSessii $dostup,
        private readonly OpredelitelRekvizitovHodaOpenCode $rekvizity,
        private readonly ZapuskatelHodaWorkspace $launcher,
        private readonly OpredelitelRezhimaAgentaHoda $rezhimyAgentov,
    ) {
    }

    public function handle(
        string $workspaceId,
        string $prompt,
        ?string $model = null,
        ?string $credentialId = null,
        ?string $agentModeId = null,
    ): RezultatZapuskaHoda {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspace = $this->workspaces->getById($workspaceId);

        $uchastnik = $this->dostup->trebovat($workspace, $accountId, RolUchastnikaSessii::WRITER);

        if ($workspace->estArhiv()) {
            throw new \DomainException('opencode.session.archived');
        }

        if ($workspace->getWorkspace()->getStatus() !== StatusWorkspace::RUNNING) {
            throw new \DomainException('opencode.workspace.not_running');
        }

        $poslednii = $this->turns->findPosledniiPoSessii($workspaceId);

        if ($poslednii !== null && $poslednii->zhdetOtveta()) {
            throw new \DomainException('opencode.turn.awaiting_answer');
        }

        $rezhimAgenta = $this->rezhimyAgentov->opredelit(
            $accountId,
            OpredelitelRezhimaAgentaHoda::CHAT_WORKSPACE,
            $agentModeId,
        );

        $istochnik = $this->rekvizity->opredelitDlyaWorkspace(
            $workspace,
            $accountId,
            $uchastnik,
            $credentialId ?? $rezhimAgenta->credentialId,
        );

        $turn = $this->launcher->zapustit(
            $workspace,
            $prompt,
            [],
            $model,
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
