<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UseCase;

use App\OpenCode\Business\Command\PoluchitSpisokWorkspace;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\RolUchastnikaSessii;
use App\OpenCode\Business\Model\SessiyaOpenCode;
use App\OpenCode\Business\Model\SessiyaOpenCodeFilter;
use App\OpenCode\Business\Model\TipSessiiOpenCode;
use App\OpenCode\Business\Model\UchastnikSessii;
use App\OpenCode\Business\Port\SessiyaOpenCodeRepository;
use App\OpenCode\Business\Port\UchastnikiSessiiRepository;
use App\OpenCode\Business\Port\UmeetProveryatRekvizityOpenCode;
use App\OpenCode\Business\Port\UmeetUpravlyatFaylamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\ReadModel\WorkspaceReadModel;
use App\OpenCode\Business\Utils\SlugWorkspace;
use App\OpenCode\Business\Workspace\Model\WorkspaceFeatureFlags;
use App\OpenCode\Business\Workspace\UseCase\PoluchitSpisokWorkspaceSessiiQuery;

/**
 * Workspace-сессии текущего пользователя с фильтрами `onlyActive` и `search`.
 */
final class PoluchitSpisokWorkspaceQuery
{
    public function __construct(
        private SessiyaOpenCodeRepository $sessionRepository,
        private UmeetProveryatRekvizityOpenCode $credentials,
        private UmeetUpravlyatProektamiWorkspace $projectami,
        private ?UmeetUpravlyatFaylamiWorkspace $fayly = null,
        private ?UchastnikiSessiiRepository $uchastniki = null,
        private ?WorkspaceFeatureFlags $flags = null,
        private ?PoluchitSpisokWorkspaceSessiiQuery $workspaceQuery = null,
    ) {
    }

    /**
     * @return list<WorkspaceReadModel>
     */
    public function handle(PoluchitSpisokWorkspace $query): array
    {
        if ($this->flags?->chitatIzNovogoAgregata() === true && $this->workspaceQuery !== null) {
            return $this->workspaceQuery->handle($query);
        }

        $accountId = $this->credentials->tekuschiiAccountId();
        $filter = new SessiyaOpenCodeFilter(
            $query->onlyActive,
            $query->search,
            TipSessiiOpenCode::WORKSPACE,
        );

        $sessions = [];

        foreach ($this->sessionRepository->findByAccountId($accountId, $filter) as $session) {
            if (!$session->estWorkspace()) {
                continue;
            }

            $sessions[$session->getId()] = $session;
        }

        // Workspace, где пользователь — участник, но не владелец.
        foreach ($this->uchastniki?->findByAccountId($accountId) ?? [] as $uchastnik) {
            if (isset($sessions[$uchastnik->getSessionId()])) {
                continue;
            }

            $session = $this->sessionRepository->findById($uchastnik->getSessionId());

            if ($session === null || !$session->estWorkspace() || !$this->sootvetstvuet($session, $filter)) {
                continue;
            }

            $sessions[$session->getId()] = $session;
        }

        $files = $this->fayly?->poluchitFaylyPoImId(array_keys($sessions)) ?? [];
        $uchastnikiPoSessii = $this->uchastniki?->findBySessionIds(array_keys($sessions)) ?? [];
        $result = [];

        foreach ($sessions as $session) {
            [$myRole, $participantsCount, $shared] = $this->rolIStruktura(
                $session,
                $accountId,
                $uchastnikiPoSessii[$session->getId()] ?? [],
            );

            $result[] = WorkspaceReadModel::izModela(
                $session,
                $this->ideLink($session, $accountId),
                $files[$session->getId()] ?? [],
                $this->sessionRepository->findWorkspaceUpdate($session->getId()) !== null,
                $myRole,
                $participantsCount,
                $shared,
            );
        }

        return $result;
    }

    /**
     * Роль текущего пользователя, число участников и признак «общий».
     *
     * Владелец всегда считается участником, даже если строка ещё не создана
     * backfill'ом. «Общий» — есть хотя бы один участник кроме владельца.
     *
     * @param list<UchastnikSessii> $uchastniki
     *
     * @return array{string, int, bool}
     */
    private function rolIStruktura(SessiyaOpenCode $session, string $accountId, array $uchastniki): array
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
            $accounts[$session->getAccountId()] = true;
        }

        if ($myRole === null) {
            $myRole = $session->prinadlezhit($accountId)
                ? RolUchastnikaSessii::OWNER
                : RolUchastnikaSessii::VIEWER;
        }

        $count = count($accounts);

        return [$myRole->value, $count, $count > 1];
    }

    /**
     * Фильтр участника применяется в PHP: выборка по account_id не умеет
     * фильтровать join-ом по имени и архиву без второго запроса.
     */
    private function sootvetstvuet(SessiyaOpenCode $session, SessiyaOpenCodeFilter $filter): bool
    {
        if ($filter->isOnlyActive() && $session->estArhiv()) {
            return false;
        }

        if ($filter->estPoisk() && mb_stripos($session->getTitle(), (string) $filter->getSearch()) === false) {
            return false;
        }

        return true;
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
