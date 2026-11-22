<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\OtvetNaVopros;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\WorkspaceKontekst;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\ReadModel\RezultatZapuskaHoda;
use App\OpenCode\Business\Utils\IstoriyaDialogaOpenCode;
use App\OpenCode\Business\Utils\OtvetyNaVoprosyOpenCode;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

final class OtvetitNaVoprosWorkspaceUseCase
{
    public function __construct(
        private WorkspaceSessionRepository $workspaces,
        private WorkspaceTurnRepository $turns,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetVypolnyatHodOpenCode $runner,
        private ProverkaDostupaWorkspaceSessii $dostup,
        private UuidGenerator $uuidGenerator,
    ) {
    }

    /**
     * @param list<array{QuestionId?: mixed, OptionId?: mixed, Text?: mixed}> $answers
     */
    public function handle(string $turnId, array $answers): RezultatZapuskaHoda
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $turn = $this->turns->getById($turnId);
        $workspace = $this->workspaces->getById($turn->getSessionId());
        $this->dostup->proverit($workspace, $accountId);

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

        $readOnly = $workspace->getWorkspace()->isReadOnly();
        $now = time();
        $novyiHod = HodOpenCode::create(
            $this->uuidGenerator->makeGuid(),
            $workspace->getId(),
            $this->turns->sleduyushiiSeq($workspace->getId()),
            $this->uuidGenerator->makeGuid(),
            $prompt,
            $normalizovannie,
            $now,
        );

        $history = IstoriyaDialogaOpenCode::sobrat(
            $this->turns->findBySessionId($workspace->getId()),
        );

        $kontekst = new WorkspaceKontekst(
            $workspace->getId(),
            $readOnly,
            $workspace->getWorkspace()->getProjects(),
        );

        $this->runner->nachatHod(
            $novyiHod->getWorkflowId(),
            $accountId,
            $workspace->getCredentialId(),
            $workspace->getExternalSessionId(),
            [],
            $prompt,
            $normalizovannie,
            $history,
            $kontekst,
            $readOnly,
            null,
        );

        $this->turns->save($novyiHod);
        $workspace->otmetitIzmenenie($now);
        $this->workspaces->update($workspace);

        return new RezultatZapuskaHoda(
            $novyiHod->getId(),
            $novyiHod->getStatus()->value,
            $this->runner->kanalProgressa($novyiHod->getWorkflowId()),
        );
    }
}
