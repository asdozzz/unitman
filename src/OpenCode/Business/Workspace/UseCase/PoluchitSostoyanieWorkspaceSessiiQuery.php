<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Model\WorkspaceUnit;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\ReadModel\ProektWorkspaceReadModel;
use App\OpenCode\Business\ReadModel\SostoyanieWorkspaceReadModel;
use App\OpenCode\Business\ReadModel\UchastnikSessiiReadModel;
use App\OpenCode\Business\ReadModel\WorkspaceUnitReadModel;
use App\OpenCode\Business\Utils\SlugWorkspace;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceTurnRepository;
use App\OpenCode\Business\Workspace\Utils\ProverkaDostupaWorkspaceSessii;

final class PoluchitSostoyanieWorkspaceSessiiQuery
{
    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly WorkspaceTurnRepository $turns,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly ProverkaDostupaWorkspaceSessii $dostup,
        private readonly WorkspaceParticipantsRepository $uchastniki,
        private readonly UmeetUpravlyatProektamiWorkspace $projectami,
        private readonly ?UmeetUpravlyatFaylamiWorkspace $fayly = null,
    ) {
    }

    /**
     * Контракт нового маршрута `workspace-session/sostoyanie`.
     *
     * @return array<string, mixed>
     */
    public function handle(string $workspaceId): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspace = $this->workspaces->getById($workspaceId);
        $this->dostup->proverit($workspace, $accountId);

        $state = $workspace->getWorkspace();
        $poslednii = $this->turns->findPosledniiPoSessii($workspaceId);
        $canAccept = !$workspace->estArhiv()
            && $state->getStatus() === StatusWorkspace::RUNNING
            && ($poslednii === null || $poslednii->getStatus()->value !== 'RUNNING');

        return [
            'workspaceId' => $workspace->getId(),
            'status' => $state->getStatus()->value,
            'archived' => $workspace->estArhiv(),
            'archiveReason' => $workspace->getArchiveReason(),
            'canAcceptMessages' => $canAccept,
            'readOnly' => $state->isReadOnly(),
            'projects' => array_map(
                static fn (ProektWorkspace $project): array => [
                    'projectId' => $project->projectId,
                    'projectName' => $project->projectName,
                    'projectCode' => $project->projectCode,
                    'canPush' => $project->canPush,
                    'positionIndex' => $project->positionIndex,
                    'branch' => $project->branch,
                ],
                $state->getProjects(),
            ),
            'lastTurn' => $poslednii?->toArray(),
        ];
    }

    /**
     * Полный контракт легаси-состояния для cutover чтений.
     *
     * Единицы (созданные юниты) и `updating` остаются за фазой 7: новый
     * репозиторий пока их не читает. Остальные поля совпадают по смыслу с
     * [`PoluchitSostoyanieWorkspaceQuery`](unitman/src/OpenCode/Business/UseCase/PoluchitSostoyanieWorkspaceQuery.php).
     *
     * @throws \DomainException `opencode.session.not_found` для не-участника
     */
    public function handleReadModel(string $workspaceId): SostoyanieWorkspaceReadModel
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspace = $this->workspaces->getById($workspaceId);

        $rol = $this->dostup->rol($workspace, $accountId);

        if ($rol === null) {
            throw new \DomainException('opencode.session.not_found');
        }

        $uchastnik = $this->uchastniki->find($workspaceId, $accountId);
        $state = $workspace->getWorkspace();
        $now = time();
        $poslednii = $this->turns->findPosledniiPoSessii($workspaceId);
        $updating = $this->workspaces->findPendingUpdate($workspaceId) !== null;

        $canAccept = $rol->mozhetPisat()
            && $state->getStatus() === StatusWorkspace::RUNNING
            && !$workspace->estArhiv()
            && !$updating
            && !($poslednii !== null && $poslednii->estVProzesse())
            && !$this->turns->zanyata(
                $workspaceId,
                $now,
                WorkspaceTurnRepository::TTL_ZANYATOSTI_SEKUND,
            );

        return new SostoyanieWorkspaceReadModel(
            $workspace->getId(),
            $workspace->getName(),
            $state->getBranch(),
            $state->getStatus()->value,
            $state->isReadOnly(),
            $workspace->estArhiv(),
            $workspace->getArchiveReason(),
            $state->getErrorKey(),
            array_map(
                static fn (ProektWorkspace $project): ProektWorkspaceReadModel => ProektWorkspaceReadModel::izModela($project),
                $state->getProjects(),
            ),
            $this->ideLink($workspace, $accountId),
            $canAccept,
            $poslednii?->getId(),
            $poslednii?->getStatus()->value,
            array_map(
                static fn (WorkspaceUnit $unit): WorkspaceUnitReadModel => WorkspaceUnitReadModel::izModela($unit),
                $state->getUnits(),
            ),
            $this->fayly?->poluchitFayly($workspaceId) ?? [],
            $updating,
            $rol->value,
            $rol->mozhetUpravlyat(),
            $this->poluchitUchastnikov($workspace),
            $uchastnik !== null ? $uchastnik->getDefaultCredentialId() : $workspace->getCredentialId(),
            $uchastnik !== null ? $uchastnik->getDefaultModel() : $workspace->getModel(),
        );
    }

    /**
     * @return list<UchastnikSessiiReadModel>
     */
    private function poluchitUchastnikov(WorkspaceSession $workspace): array
    {
        $uchastniki = $this->uchastniki->findByWorkspaceId($workspace->getId());
        $estVladelec = false;

        foreach ($uchastniki as $uchastnik) {
            if ($uchastnik->estVladelec()) {
                $estVladelec = true;
                break;
            }
        }

        if (!$estVladelec) {
            array_unshift($uchastniki, UchastnikSessii::restore(
                $workspace->getId(),
                $workspace->getAccountId(),
                RolUchastnikaSessii::OWNER,
                $workspace->getCredentialId(),
                $workspace->getModel(),
                null,
                $workspace->getCreatedAt(),
                $workspace->getUpdatedAt(),
            ));
        }

        return array_map(
            static fn (UchastnikSessii $uchastnik): UchastnikSessiiReadModel
                => UchastnikSessiiReadModel::izModela($uchastnik),
            $uchastniki,
        );
    }

    private function ideLink(WorkspaceSession $workspace, string $accountId): ?string
    {
        $first = $workspace->getWorkspace()->getProjects()[0] ?? null;

        if (!$first instanceof ProektWorkspace) {
            return null;
        }

        try {
            return $this->projectami->poluchitSsylkuRedaktora(
                $first->projectId,
                $accountId,
                SlugWorkspace::dlya($workspace->getName(), $workspace->getId()),
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
