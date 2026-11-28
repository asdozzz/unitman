<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\AgentMode\Utils\OpredelitelRezhimaAgentaHoda;
use App\OpenCode\Business\Command\OtvetNaVopros;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\ReadModel\RezultatZapuskaHoda;
use App\OpenCode\Business\Utils\IstoriyaDialogaOpenCode;
use App\OpenCode\Business\Utils\OpredelitelRekvizitovHodaOpenCode;
use App\OpenCode\Business\Utils\OtvetyNaVoprosyOpenCode;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;
use App\OpenCode\Business\Workspace\Utils\ZapuskatelHodaWorkspace;

/**
 * Ответ на вопрос прошлого хода workspace на самостоятельном агрегате.
 */
final class OtvetitNaVoprosWorkspaceUseCase
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

    /**
     * @param list<mixed> $answers сырые ответы из тела запроса, валидируются ниже
     */
    public function handle(
        string $turnId,
        array $answers,
        ?string $model = null,
        ?string $credentialId = null,
        ?string $agentModeId = null,
    ): RezultatZapuskaHoda {
        $accountId = $this->credentials->tekuschiiAccountId();
        $turn = $this->turns->getById($turnId);
        $workspace = $this->workspaces->getById($turn->getSessionId());

        $uchastnik = $this->dostup->trebovat($workspace, $accountId, RolUchastnikaSessii::WRITER);

        if ($workspace->estArhiv()) {
            throw new \DomainException('opencode.session.archived');
        }

        if ($workspace->getWorkspace()->getStatus() !== StatusWorkspace::RUNNING) {
            throw new \DomainException('opencode.workspace.not_running');
        }

        if (!$turn->zhdetOtveta()) {
            throw new \DomainException('opencode.turn.not_awaiting_answer');
        }

        $questions = $turn->getResult()?->questions ?? [];
        $otvety = [];

        foreach ($answers as $answer) {
            if (!is_array($answer)) {
                continue;
            }

            $otvety[] = new OtvetNaVopros(
                is_string($answer['QuestionId'] ?? null) ? $answer['QuestionId'] : '',
                is_string($answer['OptionId'] ?? null) ? $answer['OptionId'] : null,
                is_string($answer['Text'] ?? null) ? $answer['Text'] : null,
            );
        }

        $normalizovannie = OtvetyNaVoprosyOpenCode::sobrat($questions, $otvety);
        $prompt = IstoriyaDialogaOpenCode::otvetyVTekst($questions, $normalizovannie);

        if (trim($prompt) === '') {
            throw new \DomainException('opencode.turn.answers_required');
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

        $novyiHod = $this->launcher->zapustit(
            $workspace,
            $prompt,
            $normalizovannie,
            $model,
            $istochnik,
            $rezhimAgenta,
        );

        return new RezultatZapuskaHoda(
            $novyiHod->getId(),
            $novyiHod->getStatus()->value,
            $this->runner->kanalProgressa($novyiHod->getWorkflowId()),
        );
    }
}
