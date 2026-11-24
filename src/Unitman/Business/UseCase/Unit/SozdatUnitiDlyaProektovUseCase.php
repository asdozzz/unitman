<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\SozdatUnit\ZnacheniePeremenoi;
use App\Unitman\Business\Command\Unit\SozdatUnitiDlyaProektov;
use App\Unitman\Business\Command\Unit\SozdatUnitiDlyaProektov\PlanSozdaniyaUnita;
use App\Unitman\Business\Command\Unit\SozdatUnitiDlyaProektov\ProektDlyaSozdaniyaUnita;
use App\Unitman\Business\Model\Unit\ConfigUnita;
use App\Unitman\Business\Model\Unit\UnitBranch;
use App\Unitman\Business\Model\Unit\UnitName;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\CanParseYaml;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatKonfigProekta;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\RunnerPublicAddressProvider;
use App\Unitman\Business\Port\Unit\CanFindUnitDouble;
use App\Unitman\Business\Port\Unit\UmeetSozdavatUnit;
use App\Runner\Business\UseCase\RunnerSelectionService;
use App\Unitman\Business\ReadModel\Unit\SozdannieUnitiReadModel;
use App\Unitman\Business\ReadModel\Unit\SozdanniyUnitReadModel;
use App\Unitman\Business\Utils\SsylkaServisaUtil;
use App\Utils\Exception\TranslatorKeyword;

/**
 * Групповое создание юнитов для набора проектов с автоматическими связями
 * через хелперы переменных.
 *
 * Все проверки выполняются до первой записи: если конфиг хотя бы одного
 * проекта не читается, имя занято или публичный адрес раннера невалиден, не создаётся
 * ни один юнит.
 */
final class SozdatUnitiDlyaProektovUseCase
{
    private const MAX_PROJECTS = 20;

    public function __construct(
        private ProjectRepository $projectRepository,
        private RepoRepository $repoRepository,
        private UmeetPoluchatSpisokProektovDlyPolzovatelya $spisokProektovDlyPolzovatelya,
        private UmeetPoluchatKonfigProekta $konfigProekta,
        private CanParseYaml $canParseYaml,
        private CanFindUnitDouble $canFindUnitDouble,
        private CanGenerateGuid $uuidGenerator,
        private UmeetSozdavatUnit $sozdatUnitUseCase,
        // Multi-runner: раннер выбирается один раз до создания юнитов, чтобы
        // ссылки-хелперы и фактическая привязка юнитов совпадали. Без этих
        // сервисов (legacy-тесты) ссылки на сервисы посчитать нельзя.
        private ?RunnerSelectionService $runnerSelectionService = null,
        private ?RunnerPublicAddressProvider $runnerPublicAddressProvider = null,
    ) {
    }

    /**
     * @throws \DomainException если настройки, конфиг или имена не проходят проверку
     */
    public function handleDlyaPolzovatelya(
        SozdatUnitiDlyaProektov $command,
        string $accountId,
    ): SozdannieUnitiReadModel {
        $unitName = trim($command->unitName);
        new UnitName($unitName);

        $projects = $this->normalizeProjects($command->projects);

        if ($projects === []) {
            throw new \DomainException('unit.projects_required');
        }

        if (count($projects) > self::MAX_PROJECTS) {
            throw new \DomainException('unit.projects_too_many');
        }

        $dostupnieProekti = $this->dostupnieProekti($accountId);
        $plans = [];

        $runnerId = $this->vybratRunnerId();

        foreach ($projects as $projectRequest) {
            if (!isset($dostupnieProekti[$projectRequest->projectId])) {
                throw new \DomainException('unit.project_not_found');
            }

            $project = $this->projectRepository->getById($projectRequest->projectId);

            if ($project->isDisable()) {
                throw new \DomainException('unit.project_is_disabled');
            }

            $branch = trim($projectRequest->branch);
            new UnitBranch($branch);

            if ($this->canFindUnitDouble->isExistsDoubleByName($project->getId(), $unitName)) {
                throw new \DomainException(TranslatorKeyword::UNIT_NAME_ALREADY_EXISTS->value);
            }

            $repo = $this->repoRepository->getById($project->getRepoId());
            $configRaw = $this->konfigProekta->poluchitKonfigIzHranilisha(
                $repo,
                $project->getCode(),
                $branch,
            );

            if (trim($configRaw) === '') {
                throw new \DomainException('unit.config_is_empty');
            }

            $config = ConfigUnita::fromArray($this->canParseYaml->parse($configRaw));

            $plans[] = new PlanSozdaniyaUnita(
                $project->getId(),
                $project->getName(),
                $project->getCode(),
                $branch,
                $this->uuidGenerator->makeGuid(),
                $this->uuidGenerator->makeGuid(),
                $command->memoryLimit ?? $project->poluchitMemoryLimit(),
                $config,
                $runnerId,
            );
        }

        $poProjectId = [];
        $poProjectCode = [];

        foreach ($plans as $plan) {
            $poProjectId[$plan->projectId] = $plan;
            $poProjectCode[$plan->projectCode] ??= $plan;
        }

        $result = [];

        foreach ($plans as $plan) {
            $values = $this->sobratZnacheniyaPeremenih($plan, $poProjectId, $poProjectCode, $unitName);

            $this->sozdatUnitUseCase->sozdatDlyaPolzovatelya(
                new SozdatUnit(
                    $plan->projectId,
                    $unitName,
                    $plan->branch,
                    $values,
                    $plan->memoryLimit,
                ),
                $accountId,
                $plan->unitId,
                $plan->prozesId,
                false,
                $plan->runnerId,
            );

            $result[] = new SozdanniyUnitReadModel(
                $plan->unitId,
                $plan->projectId,
                $unitName,
                $plan->branch,
            );
        }

        return new SozdannieUnitiReadModel($result);
    }

    /**
     * @param PlanSozdaniyaUnita[] $plans
     * @param array<string, PlanSozdaniyaUnita> $poProjectId
     * @param array<string, PlanSozdaniyaUnita> $poProjectCode
     *
     * @return ZnacheniePeremenoi[]
     */
    private function sobratZnacheniyaPeremenih(
        PlanSozdaniyaUnita $plan,
        array $poProjectId,
        array $poProjectCode,
        string $unitName,
    ): array {
        $values = [];

        foreach ($plan->config->getVariables() as $variable) {
            $value = $variable->getDefaultValue();
            $targetUnitId = null;
            $helper = $variable->poluchitPomoschnikaSsylki();

            if ($helper !== null) {
                // В конфигах встречается и Unitman projectId, и код проекта.
                $target = $poProjectId[$helper->projectId]
                    ?? $poProjectCode[$helper->projectId]
                    ?? null;

                // Один юнит на проект: хелпер на свой же проект не создаёт
                // self-link, который запрещён доменом.
                if ($target !== null && $target->projectId !== $plan->projectId) {
                    $value = SsylkaServisaUtil::sformirovat(
                        $helper->type,
                        $helper->port,
                        $unitName,
                        $target->projectName,
                        $this->poluchitPublicAddress($target, $helper->type),
                    );

                    if ($helper->suffix !== null && trim($helper->suffix) !== '') {
                        $value .= str_starts_with($helper->suffix, '/')
                            ? $helper->suffix
                            : '/'.$helper->suffix;
                    }

                    $targetUnitId = $target->unitId;
                }
            }

            $values[] = new ZnacheniePeremenoi(
                $variable->getId(),
                $value,
                $variable->getType(),
                $targetUnitId,
            );
        }

        return $values;
    }

    /**
     * Раннер для всех создаваемых юнитов выбирается один раз, чтобы
     * ссылки-хелперы и привязка совпадали. Живых раннеров нет — создание
     * невозможно: `runner.not_found` до первой записи.
     *
     * `null` возвращается только когда сервис multi-runner не подключён
     * (legacy/ручная сборка тестов).
     *
     * @throws \DomainException `runner.not_found`
     */
    private function vybratRunnerId(): ?string
    {
        if ($this->runnerSelectionService === null) {
            return null;
        }

        return $this->runnerSelectionService->select()->getId();
    }

    /**
     * Публичный адрес (`publicAddress`) раннера, к которому будет привязана
     * цель.
     *
     * @throws \DomainException `unit.runner_not_bound` | `unit.runner_public_address_missing`
     */
    private function poluchitPublicAddress(PlanSozdaniyaUnita $target, ?string $type): ?string
    {
        if (!in_array(SsylkaServisaUtil::normalizovatTip($type), ['http', 'ws', 'wss'], true)) {
            return null;
        }

        if ($target->runnerId === null || $target->runnerId === '') {
            throw new \DomainException('unit.runner_not_bound');
        }

        if ($this->runnerPublicAddressProvider === null) {
            throw new \DomainException('unit.runner_public_address_missing');
        }

        $publicAddress = $this->runnerPublicAddressProvider->getPublicAddress($target->runnerId);

        if ($publicAddress === null || trim($publicAddress) === '') {
            throw new \DomainException('unit.runner_public_address_missing');
        }

        return $publicAddress;
    }

    /**
     * @param ProektDlyaSozdaniyaUnita[] $projects
     *
     * @return ProektDlyaSozdaniyaUnita[]
     */
    private function normalizeProjects(array $projects): array
    {
        $result = [];
        $uvidennie = [];

        foreach ($projects as $project) {
            if (!$project instanceof ProektDlyaSozdaniyaUnita) {
                throw new \DomainException('unit.projects_invalid');
            }

            $projectId = trim($project->projectId);

            if ($projectId === '') {
                continue;
            }

            if (isset($uvidennie[$projectId])) {
                throw new \DomainException('unit.projects_conflict');
            }

            $uvidennie[$projectId] = true;
            $result[] = new ProektDlyaSozdaniyaUnita($projectId, $project->branch);
        }

        return $result;
    }

    /**
     * @return array<string, true>
     */
    private function dostupnieProekti(string $accountId): array
    {
        $result = [];

        foreach ($this->spisokProektovDlyPolzovatelya->poluchitSpisokProektovDlyPolzovatelya($accountId) as $project) {
            if (!is_array($project)) {
                continue;
            }

            $projectId = $project['id'] ?? null;

            if (is_string($projectId) && $projectId !== '') {
                $result[$projectId] = true;
            }
        }

        return $result;
    }
}
