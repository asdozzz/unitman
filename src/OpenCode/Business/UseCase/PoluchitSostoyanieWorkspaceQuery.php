<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSostoyanieWorkspace;
use App\OpenCode\Business\Model\HodOpenCode;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\StatusWorkspace;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Model\WorkspaceUnit;
use App\OpenCode\Business\Port\HodOpenCodeRepository;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\ReadModel\ProektWorkspaceReadModel;
use App\OpenCode\Business\ReadModel\SostoyanieWorkspaceReadModel;
use App\OpenCode\Business\ReadModel\UchastnikSessiiReadModel;
use App\OpenCode\Business\ReadModel\WorkspaceUnitReadModel;
use App\OpenCode\Business\Utils\ProverkaDostupaKSessii;
use App\OpenCode\Business\Utils\SlugWorkspace;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\UseCase\PoluchitSostoyanieWorkspaceSessiiQuery;

/**
 * Состояние workspace для интерфейса.
 *
 * `canAcceptMessages` повторяет правило гейта: статус RUNNING, сессия не в
 * архиве, нет обновления, последний ход не выполняется и роль позволяет
 * писать. Свежая резервация хода тоже делает сессию занятой, а просроченная —
 * нет: она будет перехвачена при следующем старте.
 */
final class PoluchitSostoyanieWorkspaceQuery
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private HodOpenCodeRepository $turnRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatProektamiWorkspace $projectami,
        private ProverkaDostupaKSessii $dostup,
        private UchastnikiSessiiRepository $uchastniki,
        private ?UmeetUpravlyatFaylamiWorkspace $fayly = null,
        private ?WorkspaceFeatureFlags $flags = null,
        private ?PoluchitSostoyanieWorkspaceSessiiQuery $workspaceQuery = null,
    ) {
    }

    /**
     * @throws \DomainException если workspace не найден или пользователь не участник
     */
    public function handle(PoluchitSostoyanieWorkspace $query): SostoyanieWorkspaceReadModel
    {
        $accountId = $this->credentials->tekuschiiAccountId();
        $workspaceId = trim($query->workspaceId);

        if ($workspaceId === '') {
            throw new \DomainException('opencode.workspace.not_found');
        }

        if ($this->flags?->chitatIzNovogoAgregata() === true && $this->workspaceQuery !== null) {
            return $this->workspaceQuery->handleReadModel($workspaceId);
        }

        $session = $this->sessionRepository->getById($workspaceId);
        $uchastnik = $this->dostup->trebovat($session, $accountId, RolUchastnikaSessii::VIEWER);

        $workspace = $session->getWorkspace();

        if (!$session->estWorkspace() || $workspace === null) {
            throw new \DomainException('opencode.workspace.not_found');
        }

        $rol = $uchastnik?->getRol() ?? RolUchastnikaSessii::OWNER;
        $now = time();

        $lastTurn = $this->turnRepository->findPosledniiPoSessii($workspaceId);
        $updating = $this->sessionRepository->findWorkspaceUpdate($workspaceId) !== null;
        $canAcceptMessages = $rol->mozhetPisat()
            && $workspace->getStatus() === StatusWorkspace::RUNNING
            && !$session->estArhiv()
            && !$updating
            && !($lastTurn instanceof HodOpenCode && $lastTurn->estVProzesse())
            && !$this->turnRepository->zanyata(
                $workspaceId,
                $now,
                HodOpenCodeRepository::TTL_ZANYATOSTI_SEKUND,
            );

        return new SostoyanieWorkspaceReadModel(
            $session->getId(),
            $session->getTitle(),
            $workspace->getBranch(),
            $workspace->getStatus()->value,
            $workspace->isReadOnly(),
            $session->estArhiv(),
            $session->getArchiveReason(),
            $workspace->getErrorKey(),
            array_map(
                static fn (ProektWorkspace $project): ProektWorkspaceReadModel => ProektWorkspaceReadModel::izModela($project),
                $workspace->getProjects(),
            ),
            $this->ideLink($session, $accountId),
            $canAcceptMessages,
            $lastTurn?->getId(),
            $lastTurn?->getStatus()->value,
            array_map(
                static fn (WorkspaceUnit $unit): WorkspaceUnitReadModel => WorkspaceUnitReadModel::izModela($unit),
                $workspace->getUnits(),
            ),
            $this->fayly?->poluchitFayly($workspaceId) ?? [],
            $updating,
            $rol->value,
            $rol->mozhetUpravlyat(),
            $this->poluchitUchastnikov($session),
            $uchastnik !== null ? $uchastnik->getDefaultCredentialId() : $session->getCredentialId(),
            $uchastnik !== null ? $uchastnik->getDefaultModel() : $session->getModel(),
        );
    }

    /**
     * Участники в том же виде, что и `PoluchitUchastnikovQuery`: владелец
     * всегда присутствует, даже если строка ещё не создана backfill'ом.
     *
     * @return list<UchastnikSessiiReadModel>
     */
    private function poluchitUchastnikov(SessiyaOpenCode $session): array
    {
        $uchastniki = $this->uchastniki->findBySessionId($session->getId());
        $estVladelec = false;

        foreach ($uchastniki as $uchastnik) {
            if ($uchastnik->estVladelec()) {
                $estVladelec = true;
                break;
            }
        }

        if (!$estVladelec) {
            array_unshift($uchastniki, UchastnikSessii::restore(
                $session->getId(),
                $session->getAccountId(),
                RolUchastnikaSessii::OWNER,
                $session->getCredentialId(),
                $session->getModel(),
                null,
                $session->getCreatedAt(),
                $session->getUpdatedAt(),
            ));
        }

        return array_map(
            static fn (UchastnikSessii $uchastnik): UchastnikSessiiReadModel
                => UchastnikSessiiReadModel::izModela($uchastnik),
            $uchastniki,
        );
    }

    private function ideLink(SessiyaOpenCode $session, string $accountId): ?string
    {
        $first = $session->getWorkspace()?->getProjects()[0] ?? null;

        if (!$first instanceof ProektWorkspace) {
            return null;
        }

        try {
            return $this->projectami->poluchitSsylkuRedaktora(
                $first->projectId,
                $accountId,
                SlugWorkspace::dlya($session->getTitle(), $session->getId()),
            );
        } catch (\Throwable) {
            return null;
        }
    }
}
