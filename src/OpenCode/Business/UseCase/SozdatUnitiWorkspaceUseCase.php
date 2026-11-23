<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\SozdatUnitiWorkspace;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SozdanniyUnit;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\WorkspaceUnit;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\ReadModel\WorkspaceUnitReadModel;
use App\OpenCode\Business\Utils\SlugWorkspace;

/**
 * Создаёт по одному юниту на каждый проект workspace.
 *
 * Связи через хелперы вычисляет Unitman: OpenCode передаёт только проекты,
 * ветки и accountId. Результат фиксируется в снимке workspace, чтобы карточка
 * показывала созданные юниты без дополнительных запросов.
 */
final class SozdatUnitiWorkspaceUseCase
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatUnitami $unitami,
    ) {
    }

    /**
     * @return list<WorkspaceUnitReadModel>
     *
     * @throws \DomainException если workspace недоступен, не запущен или юниты уже созданы
     */
    public function handle(SozdatUnitiWorkspace $command): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspaceId = trim($command->workspaceId);

        if ($workspaceId === '') {
            throw new \DomainException('opencode.workspace.not_found');
        }

        $session = $this->sessionRepository->getById($workspaceId);
        $session->trebovatVladelca($accountId);

        $workspace = $session->getWorkspace();

        if (!$session->estWorkspace() || $workspace === null) {
            throw new \DomainException('opencode.workspace.not_found');
        }

        if ($workspace->getStatus() !== StatusWorkspace::RUNNING || $session->estArhiv()) {
            throw new \DomainException('opencode.workspace.not_running');
        }

        if ($workspace->getUnits() !== []) {
            throw new \DomainException('opencode.workspace.units_already_created');
        }

        $projects = [];

        foreach ($workspace->getProjects() as $project) {
            $projects[] = [
                'projectId' => $project->projectId,
                'branch' => $project->branch !== '' ? $project->branch : $workspace->getBranch(),
            ];
        }

        $unitName = SlugWorkspace::dlya($session->getTitle(), $session->getId());

        $created = $this->unitami->sozdatUnitiDlyaProektov($projects, $unitName, $accountId);

        $units = array_map(
            static fn(SozdanniyUnit $unit): WorkspaceUnit => new WorkspaceUnit(
                $unit->unitId,
                $unit->projectId,
                $unit->unitName,
                $unit->branch,
            ),
            $created,
        );

        $workspace->zapomnitUniti($units, time());
        $this->sessionRepository->update($session);

        return array_map(
            static fn(WorkspaceUnit $unit): WorkspaceUnitReadModel => WorkspaceUnitReadModel::izModela($unit),
            $units,
        );
    }
}
