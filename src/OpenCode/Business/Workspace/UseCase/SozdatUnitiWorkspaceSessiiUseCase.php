<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\SozdatUnitiWorkspace;
use App\OpenCode\Business\Model\SozdanniyUnit;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\WorkspaceUnit;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\ReadModel\WorkspaceUnitReadModel;
use App\OpenCode\Business\Utils\SlugWorkspace;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;

/**
 * Создание юнитов из workspace на самостоятельном агрегате.
 */
final class SozdatUnitiWorkspaceSessiiUseCase
{
    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatUnitami $unitami,
    ) {
    }

    /**
     * @return list<WorkspaceUnitReadModel>
     */
    public function handle(SozdatUnitiWorkspace $command): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspaceId = trim($command->workspaceId);

        if ($workspaceId === '') {
            throw new \DomainException('opencode.workspace.not_found');
        }

        $workspace = $this->workspaces->getById($workspaceId);
        $workspace->trebovatVladelca($accountId);

        $state = $workspace->getWorkspace();

        if ($state->getStatus() !== StatusWorkspace::RUNNING || $workspace->estArhiv()) {
            throw new \DomainException('opencode.workspace.not_running');
        }

        if ($state->getUnits() !== []) {
            throw new \DomainException('opencode.workspace.units_already_created');
        }

        $projects = [];

        foreach ($state->getProjects() as $project) {
            $projects[] = [
                'projectId' => $project->projectId,
                'branch' => $project->branch !== '' ? $project->branch : $state->getBranch(),
            ];
        }

        $unitName = SlugWorkspace::dlya($workspace->getName(), $workspace->getId());

        $created = $this->unitami->sozdatUnitiDlyaProektov($projects, $unitName, $accountId);

        $units = array_map(
            static fn (SozdanniyUnit $unit): WorkspaceUnit => new WorkspaceUnit(
                $unit->unitId,
                $unit->projectId,
                $unit->unitName,
                $unit->branch,
            ),
            $created,
        );

        $workspace->zapomnitUniti($units, time());
        $this->workspaces->update($workspace);

        return array_map(
            static fn (WorkspaceUnit $unit): WorkspaceUnitReadModel => WorkspaceUnitReadModel::izModela($unit),
            $units,
        );
    }
}
