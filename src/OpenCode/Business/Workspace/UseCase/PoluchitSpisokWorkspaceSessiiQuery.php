<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\UseCase;

use App\OpenCode\Business\Command\PoluchitSpisokWorkspace;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Model\WorkspaceUnit;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\ReadModel\ProektWorkspaceReadModel;
use App\OpenCode\Business\ReadModel\WorkspaceReadModel;
use App\OpenCode\Business\ReadModel\WorkspaceUnitReadModel;
use App\OpenCode\Business\Utils\SlugWorkspace;
use App\OpenCode\Business\Workspace\Model\WorkspaceFilter;
use App\OpenCode\Business\Workspace\Model\WorkspaceSession;
use App\OpenCode\Business\Workspace\Port\WorkspaceParticipantsRepository;
use App\OpenCode\Business\Workspace\Port\WorkspaceSessionRepository;

/**
 * Список workspace на самостоятельном агрегате: собственные и те, где
 * пользователь — участник. Все дочерние данные (проекты, юниты, файлы,
 * участники, pending) читаются батчем, без N+1.
 */
final class PoluchitSpisokWorkspaceSessiiQuery
{
    public function __construct(
        private readonly WorkspaceSessionRepository $workspaces,
        private readonly WorkspaceParticipantsRepository $uchastniki,
        private readonly UmeetProveryatRekvizityOpenCode $credentials,
        private readonly UmeetUpravlyatProektamiWorkspace $projectami,
        private readonly ?UmeetUpravlyatFaylamiWorkspace $fayly = null,
    ) {
    }

    /**
     * @return list<WorkspaceReadModel>
     */
    public function handle(PoluchitSpisokWorkspace $query): array
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $filter = new WorkspaceFilter($query->onlyActive, $query->search);

        $workspaces = $this->workspaces->findByAccountIdWithParticipation($accountId, $filter);

        if ($workspaces === []) {
            return [];
        }

        $ids = array_map(static fn (WorkspaceSession $workspace): string => $workspace->getId(), $workspaces);
        $uchastnikiPoSessii = $this->uchastniki->findByWorkspaceIds($ids);
        $pending = $this->workspaces->findPendingWorkspaceIds($ids);
        $files = $this->fayly?->poluchitFaylyPoImId($ids) ?? [];

        $result = [];

        foreach ($workspaces as $workspace) {
            [$myRole, $participantsCount, $shared] = $this->rolIStruktura(
                $workspace,
                $accountId,
                $uchastnikiPoSessii[$workspace->getId()] ?? [],
            );

            $state = $workspace->getWorkspace();

            $result[] = new WorkspaceReadModel(
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
                $workspace->getCreatedAt(),
                $workspace->getUpdatedAt(),
                array_map(
                    static fn (WorkspaceUnit $unit): WorkspaceUnitReadModel => WorkspaceUnitReadModel::izModela($unit),
                    $state->getUnits(),
                ),
                $workspace->getModel(),
                $files[$workspace->getId()] ?? [],
                isset($pending[$workspace->getId()]),
                $myRole,
                $workspace->getAccountId(),
                $participantsCount,
                $shared,
            );
        }

        return $result;
    }

    /**
     * @param list<UchastnikSessii> $uchastniki
     *
     * @return array{string, int, bool}
     */
    private function rolIStruktura(WorkspaceSession $workspace, string $accountId, array $uchastniki): array
    {
        $accounts = [];
        $myRole = null;
        $estVladelec = false;

        foreach ($uchastniki as $uchastnik) {
            $accounts[$uchastnik->getAccountId()] = true;

            if ($uchastnik->estVladelec()) {
                $estVladelec = true;
            }

            if ($uchastnik->getAccountId() === $accountId) {
                $myRole = $uchastnik->getRol();
            }
        }

        if (!$estVladelec) {
            $accounts[$workspace->getAccountId()] = true;
        }

        if ($myRole === null) {
            $myRole = $workspace->estVladelec($accountId)
                ? RolUchastnikaSessii::OWNER
                : RolUchastnikaSessii::VIEWER;
        }

        $count = count($accounts);

        return [$myRole->value, $count, $count > 1];
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
