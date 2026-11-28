<?php

declare(strict_types=1);

namespace App\OpenCode\Acl;

use App\OpenCode\Business\Model\ParametriSozdaniyaUnita;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SozdanniyUnit;
use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetPoluchatTokenRedaktirovaniyaUnita;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\Port\WorkspaceRunnerRepository;
use App\OpenCode\Business\Utils\OcenkaGotovnostiUnita;
use App\OpenCode\Business\Utils\SsylkaRedaktoraWorkspace;
use App\Unitman\Api\UnitmanApi;
use App\Unitman\Business\Port\RunnerPublicAddressProvider;
use App\Unitman\Business\Utils\SsylkaServisaUtil;
use App\Unitman\Business\ReadModel\Project\ProektDlyaWorkspaceReadModel;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\SozdatUnit\VolumeZapros;
use App\Unitman\Business\Command\Unit\SozdatUnit\ZnacheniePeremenoi;
use App\Unitman\Business\Command\Unit\SozdatUnitiDlyaProektov;
use App\Unitman\Business\Command\Unit\SozdatUnitiDlyaProektov\ProektDlyaSozdaniyaUnita;
use App\Unitman\Business\ReadModel\Unit\SozdanniyUnitReadModel;
use App\Unitman\Business\ReadModel\Unit\SostoyanieUnitaReadModel;

/**
 * Антикоррупционный слой над модулем Unitman.
 *
 * OpenCode не знает про агрегат Unit и его состояния: наружу отдаётся
 * группа готовности, вычисленная единой матрицей `OcenkaGotovnostiUnita`.
 */
final class UnitmanAdapter implements
    UmeetUpravlyatUnitami,
    UmeetBronirovatUniti,
    UmeetUpravlyatProektamiWorkspace,
    UmeetPoluchatTokenRedaktirovaniyaUnita
{
    /** Псевдо-проект workspace в публичном адресе workspace: `${slug}.workspaces`. */
    private const WORKSPACE_PSEUDO_PROJECT = 'workspaces';

    public function __construct(
        private UnitmanApi $unitmanApi,
        private OcenkaGotovnostiUnita $ocenkaGotovnostiUnita,
        private WorkspaceRunnerRepository $workspaceRunnerRepository,
        private RunnerPublicAddressProvider $runnerPublicAddressProvider,
    )
    {
    }

    public function poluchitSostoyanieUnita(string $unitId): SostoyanieUnita
    {
        $readModel = $this->unitmanApi->poluchitSostoyanieUnita($unitId);

        return $this->prevratit($readModel);
    }

    public function proveritDostupKUnitu(string $unitId, string $accountId): bool
    {
        return $this->unitmanApi->proveritDostupKUnitu($unitId, $accountId);
    }

    /**
     * Расшифрованный личный токен владельца. Acl только делегирует вызов:
     * какие токены подходят, решает Unitman.
     */
    public function poluchitTokenDlyRedaktirovaniyaUnita(string $unitId, string $accountId): string
    {
        return $this->unitmanApi->poluchitTokenDlyRedaktirovaniyaUnita($unitId, $accountId);
    }

    public function sozdatUnit(ParametriSozdaniyaUnita $parametri, string $accountId): string
    {
        return $this->unitmanApi->sozdatUnitDlyaPolzovatelya(
            $this->sobratKomandu($parametri),
            $accountId,
        );
    }

    /**
     * @param list<array{projectId: string, branch: string}> $projects
     *
     * @return list<SozdanniyUnit>
     */
    public function sozdatUnitiDlyaProektov(
        array $projects,
        string $unitName,
        string $accountId,
    ): array {
        $dtoProjects = [];

        foreach ($projects as $project) {
            $dtoProjects[] = new ProektDlyaSozdaniyaUnita(
                $project['projectId'],
                $project['branch'],
            );
        }

        $result = $this->unitmanApi->sozdatUnitiDlyaProektov(
            new SozdatUnitiDlyaProektov($unitName, $dtoProjects),
            $accountId,
        );

        return array_map(
            static fn(SozdanniyUnitReadModel $unit): SozdanniyUnit => new SozdanniyUnit(
                $unit->unitId,
                $unit->projectId,
                $unit->unitName,
                $unit->branch,
            ),
            $result->units,
        );
    }

    public function prodvinutCiklUnita(string $unitId, string $accountId): void
    {
        $this->unitmanApi->prodvinutCiklUnita($unitId, $accountId);
    }

    /**
     * Тип занятости передаётся строковой константой UnitmanApi, чтобы модуль
     * OpenCode не зависел от внутреннего enum `TipZanyatosti`.
     */
    public function zahvatit(array $unitIds, string $vladelec): void
    {
        $this->unitmanApi->zahvatitZanyatostUnitov($unitIds, $vladelec, UnitmanApi::TIP_OPENCODE_HOD);
    }

    public function osvobodit(array $unitIds, string $vladelec): void
    {
        $this->unitmanApi->osvoboditZanyatostUnitov($unitIds, $vladelec);
    }

    /**
     * @param list<string>          $projectIds
     * @param array<string, string> $projectBranches
     *
     * @return list<ProektWorkspace>
     */
    public function poluchitProekti(
        array $projectIds,
        string $accountId,
        string $branch = '',
        array $projectBranches = [],
    ): array {
        $result = [];

        foreach ($this->unitmanApi->poluchitProektiDlyaWorkspace($projectIds, $accountId, $branch, $projectBranches) as $position => $project) {
            $result[] = new ProektWorkspace(
                $project->projectId,
                $project->projectName,
                $project->projectCode,
                $project->canPush,
                $position,
                $project->mainBranch,
                $projectBranches[$project->projectId] ?? $branch,
            );
        }

        return $result;
    }

    /**
     * Проверка доступа кандидата ко всем проектам workspace.
     *
     * Unitman отдаёт только доступные проекты и бросает
     * `opencode.workspace.project_not_found` на первый недоступный — этого
     * достаточно, чтобы отклонить приглашение.
     *
     * @param list<string> $projectIds
     */
    public function estDostupKProektam(array $projectIds, string $accountId): bool
    {
        if ($projectIds === []) {
            return true;
        }

        try {
            return count($this->unitmanApi->poluchitProektiDlyaWorkspace($projectIds, $accountId)) === count($projectIds);
        } catch (\DomainException) {
            return false;
        }
    }

    public function poluchitSsylkuRedaktora(
        string $projectId,
        string $accountId,
        string $workspaceSlug,
    ): ?string {
        $projects = $this->unitmanApi->poluchitProektiDlyaWorkspace([$projectId], $accountId);
        $project = $projects[0] ?? null;

        if (!$project instanceof ProektDlyaWorkspaceReadModel) {
            return null;
        }

        return SsylkaRedaktoraWorkspace::postroit(
            $workspaceSlug,
            $this->publicAddressDlyaWorkspace($workspaceSlug),
            $project->idePort,
        );
    }

    /**
     * Workspace-контейнер называется `${slug}.workspaces`, поэтому ссылка
     * строится тем же правилом, что и у юнитов: псевдо-проект `workspaces`.
     */
    public function poluchitSsylkuPoPortu(
        string $projectId,
        string $accountId,
        string $workspaceSlug,
        int $port,
        ?string $type = null,
        ?string $suffix = null,
    ): ?string {
        $projects = $this->unitmanApi->poluchitProektiDlyaWorkspace([$projectId], $accountId);
        $project = $projects[0] ?? null;

        if (!$project instanceof ProektDlyaWorkspaceReadModel) {
            return null;
        }

        $link = SsylkaServisaUtil::sformirovat(
            $type,
            $port,
            $workspaceSlug,
            self::WORKSPACE_PSEUDO_PROJECT,
            $this->publicAddressDlyaWorkspace($workspaceSlug),
        );

        if ($suffix !== null && $suffix !== '') {
            $link .= str_starts_with($suffix, '/') ? $suffix : '/'.$suffix;
        }

        return $link;
    }

    /**
     * Публичный адрес (`publicAddress`) раннера, к которому жёстко привязан
     * workspace.
     *
     * Default/fallback host запрещён: workspace без привязки или раннер без
     * `publicAddress` — явная доменная ошибка (plans/runner-multi-runner.md §7).
     *
     * @throws \DomainException opencode.workspace.runner_not_bound|opencode.workspace.runner_public_address_missing
     */
    private function publicAddressDlyaWorkspace(string $workspaceId): string
    {
        $runnerId = $this->workspaceRunnerRepository->findRunnerIdByWorkspaceId($workspaceId);

        if ($runnerId === null || $runnerId === '') {
            throw new \DomainException('opencode.workspace.runner_not_bound');
        }

        $publicAddress = $this->runnerPublicAddressProvider->getPublicAddress($runnerId);

        if ($publicAddress === null || trim($publicAddress) === '') {
            throw new \DomainException('opencode.workspace.runner_public_address_missing');
        }

        return $publicAddress;
    }

    private function prevratit(SostoyanieUnitaReadModel $readModel): SostoyanieUnita
    {
        return new SostoyanieUnita(
            $readModel->exists,
            $readModel->code,
            $readModel->projectId,
            $readModel->name,
            $readModel->branch,
            $this->ocenkaGotovnostiUnita->ocenit(
                $readModel->exists,
                $readModel->code,
                $readModel->zanyat,
                $readModel->containerStateMismatch,
            ),
            $readModel->zanyat,
            $readModel->prichinaZanyatosti,
            $readModel->authorId,
            $readModel->redaktirovanieVklucheno,
            $readModel->vetkaZashishena,
            array_values($readModel->redaktory),
            $readModel->containerStateMismatch,
        );
    }

    private function sobratKomandu(ParametriSozdaniyaUnita $parametri): SozdatUnit
    {
        $peremenie = [];

        foreach ($parametri->peremenie as $peremenaya) {
            $peremenie[] = new ZnacheniePeremenoi(
                $peremenaya['id'],
                $peremenaya['value'],
                $peremenaya['type'],
                $peremenaya['targetUnitId'] ?? null,
            );
        }

        $volumes = [];

        foreach ($parametri->volumes as $volume) {
            $volumes[] = new VolumeZapros(
                $volume['dir'],
                $volume['name'],
                $volume['fileId'] ?? null,
            );
        }

        return new SozdatUnit(
            $parametri->projectId,
            $parametri->unitName,
            $parametri->branch,
            $peremenie,
            $parametri->memoryLimit,
            $volumes,
        );
    }
}
