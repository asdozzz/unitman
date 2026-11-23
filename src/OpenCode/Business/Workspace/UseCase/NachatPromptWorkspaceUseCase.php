<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\WorkspaceKontekst;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetVypolnyatHodOpenCode;
use App\OpenCode\Business\Port\UuidGenerator;
use App\OpenCode\Business\ReadModel\RezultatZapuskaHoda;
use App\OpenCode\Business\Utils\IstoriyaDialogaOpenCode;
use App\OpenCode\Business\Utils\OpredelitelModeliHodaOpenCode;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

final class NachatPromptWorkspaceUseCase
{
    public function __construct(
        private WorkspaceSessionRepository $workspaces,
        private WorkspaceTurnRepository $turns,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetVypolnyatHodOpenCode $runner,
        private ProverkaDostupaWorkspaceSessii $dostup,
        private UuidGenerator $uuidGenerator,
        private OpredelitelModeliHodaOpenCode $modeli,
    ) {
    }

    public function handle(string $workspaceId, string $prompt, ?string $model = null): RezultatZapuskaHoda
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspace = $this->workspaces->getById($workspaceId);
        $this->dostup->proverit($workspace, $accountId);

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

        $razreshennayaModel = $this->modeli->opredelit(
            $accountId,
            $workspace->getCredentialId(),
            $model,
            $workspace->getModel(),
        );

        $readOnly = $workspace->getWorkspace()->isReadOnly();
        $now = time();
        $turn = HodOpenCode::create(
            $this->uuidGenerator->makeGuid(),
            $workspace->getId(),
            $this->turns->sleduyushiiSeq($workspace->getId()),
            $this->uuidGenerator->makeGuid(),
            $prompt,
            [],
            $now,
            $razreshennayaModel,
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
            $turn->getWorkflowId(),
            $accountId,
            $workspace->getCredentialId(),
            $workspace->getExternalSessionId(),
            [],
            $prompt,
            [],
            $history,
            $kontekst,
            $readOnly,
            null,
            model: $razreshennayaModel,
        );

        $this->turns->save($turn);
        $workspace->otmetitIzmenenie($now);
        $this->workspaces->update($workspace);

        return new RezultatZapuskaHoda(
            $turn->getId(),
            $turn->getStatus()->value,
            $this->runner->kanalProgressa($turn->getWorkflowId()),
        );
    }
}
