<?php

declare(strict_types=1);

namespace App\OpenCode\Acl;

use App\OpenCode\Business\Model\ParametriSozdaniyaUnita;
use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\SostoyanieUnita;
use App\OpenCode\Business\Port\UmeetBronirovatUniti;
use App\OpenCode\Business\Port\UmeetPoluchatTokenRedaktirovaniyaUnita;
use App\OpenCode\Business\Port\UmeetUpravlyatProektamiWorkspace;
use App\OpenCode\Business\Port\UmeetUpravlyatUnitami;
use App\OpenCode\Business\Utils\OcenkaGotovnostiUnita;
use App\OpenCode\Business\Utils\SsylkaRedaktoraWorkspace;
use App\Unitman\Api\UnitmanApi;
use App\Unitman\Business\Utils\SsylkaServisaUtil;
use App\Unitman\Business\ReadModel\Project\ProektDlyaWorkspaceReadModel;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\SozdatUnit\VolumeZapros;
use App\Unitman\Business\Command\Unit\SozdatUnit\ZnacheniePeremenoi;
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
    /** Псевдо-проект workspace в proxy-хосте: `${slug}.workspaces`. */
    private const WORKSPACE_PSEUDO_PROJECT = 'workspaces';

    public function __construct(
        private UnitmanApi $unitmanApi,
        private OcenkaGotovnostiUnita $ocenkaGotovnostiUnita,
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

        return SsylkaRedaktoraWorkspace::postroit($workspaceSlug, $project->proxyHost, $project->idePort);
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

        if ($project->proxyHost === null || trim($project->proxyHost) === '') {
            return null;
        }

        try {
            $link = SsylkaServisaUtil::sformirovat(
                $type,
                $port,
                $workspaceSlug,
                self::WORKSPACE_PSEUDO_PROJECT,
                $project->proxyHost,
            );
        } catch (\DomainException) {
            // Пустой или некорректный proxy host: ссылку построить нельзя,
            // но workspace при этом продолжает работать.
            return null;
        }

        if ($suffix !== null && $suffix !== '') {
            $link .= str_starts_with($suffix, '/') ? $suffix : '/'.$suffix;
        }

        return $link;
    }

    private function prevratit(SostoyanieUnitaReadModel $readModel): SostoyanieUnita
    {
        return new SostoyanieUnita(
            $readModel->exists,
            $readModel->code,
            $readModel->projectId,
            $readModel->name,
            $readModel->branch,
            $this->ocenkaGotovnostiUnita->ocenit($readModel->exists, $readModel->code, $readModel->zanyat),
            $readModel->zanyat,
            $readModel->prichinaZanyatosti,
            $readModel->authorId,
            $readModel->redaktirovanieVklucheno,
            $readModel->vetkaZashishena,
            array_values($readModel->redaktory),
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
