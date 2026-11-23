<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\PeremennayaSvyaz;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\Unit\CanFindUnitDouble;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\Unit\UmeetSozdavatUnit;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Utils\Exception\TranslatorKeyword;

final class SozdatUnitUseCase implements UmeetSozdavatUnit
{
    public function __construct(
        private CanFindUnitDouble      $canFindUnitDouble,
        private ProjectRepository      $projectRepository,
        private UnitmanSecurityService $securityService,
        private CanGenerateGuid         $uuidGenerator,
        private UnitRepository         $unitRepository,
        private MediatekaService       $mediatekaService,
        private ProverkaFaylovVolumov  $proverkaFaylovVolumov,
        private ProverkaSvyazeyPeremenih $proverkaSvyazeyPeremenih,
        private UmeetPoluchatSpisokProektovDlyPolzovatelya $umeetPoluchatSpisokProektovDlyPolzovatelya,
    )
    {
    }

    function handle(SozdatUnit $command): string
    {
        return $this->sozdatDlyaPolzovatelya($command, $this->securityService->getCurrentUserId());
    }

    /**
     * Создание юнита с информационным признаком защищённой ветки (plan §7.3).
     *
     * Юнит создаётся штатно; `vetkaZashishena` носит информационный характер.
     *
     * @return array{id: string, vetkaZashishena: bool}
     */
    function handleSVetkoy(SozdatUnit $command): array
    {
        $project = $this->projectRepository->getById($command->projectId);
        $unitId = $this->handle($command);

        return [
            'id' => $unitId,
            'vetkaZashishena' => $project->soderzhitZashishennuyuVetku($command->branch),
        ];
    }

    /**
     * Создание юнита от имени явно переданного пользователя: этим путём
     * пользуются интеграции (сессии OpenCode), у которых нет текущего запроса.
     * Права проверяются по тому же пользователю, что и в обычном сценарии.
     *
     * `$unitId`/`$prozesId` позволяют заранее зарезервировать идентификаторы
     * для группового создания: связи на целевые юниты проставляются ещё до
     * появления их read-моделей.
     *
     * `$proveritSvyazi = false` используется только групповым сценарием, где
     * все цели уже проверены по плану и ещё не существуют в read-модели.
     */
    public function sozdatDlyaPolzovatelya(
        SozdatUnit $command,
        string $userId,
        ?string $unitId = null,
        ?string $prozesId = null,
        bool $proveritSvyazi = true,
    ): string
    {
        if ($this->canFindUnitDouble->isExistsDoubleByName($command->projectId, $command->unitName)) {
            throw new \DomainException(TranslatorKeyword::UNIT_NAME_ALREADY_EXISTS->value);
        }

        $project = $this->projectRepository->getById($command->projectId);

        if ($project->isDisable()) {
            throw new \DomainException('unit.project_is_disabled');
        }

        // Связи переменных с юнитами, выбранными через хелпер, проверяются
        // до записи событий: цель должна существовать и быть доступной.
        if ($proveritSvyazi) {
            $this->proverkaSvyazeyPeremenih->proverit(
                $this->makeSvyazi($command),
                null,
                array_map(
                    fn(array $project): string => $project['id'],
                    $this->umeetPoluchatSpisokProektovDlyPolzovatelya->poluchitSpisokProektovDlyPolzovatelya($userId)
                )
            );
        }

        // Проверяем файлы томов до сохранения юнита: черновики будут привязаны
        // к новому юниту, архивы каталога проекта остаются общими.
        $chernoviki = $this->proverkaFaylovVolumov->proverit($command->projectId, $command->volumes);

        $unitId ??= $this->uuidGenerator->makeGuid();
        $prozesId ??= $this->uuidGenerator->makeGuid();
        $unit = Unit::sozdatUnit($unitId, $userId, $prozesId, $command);
        $this->unitRepository->save($unit);

        if ($chernoviki !== []) {
            $this->mediatekaService->prikrepitFaylyKUnitu($chernoviki, $unitId);
        }

        return $unit->getId();
    }

    /**
     * @return PeremennayaSvyaz[]
     */
    private function makeSvyazi(SozdatUnit $command): array
    {
        $result = [];

        foreach ($command->znacheniePeremenoi as $peremenaya) {
            $targetUnitId = $peremenaya->targetUnitId;

            if ($targetUnitId === null || trim($targetUnitId) === '') {
                continue;
            }

            $result[] = new PeremennayaSvyaz($peremenaya->id, $targetUnitId);
        }

        return $result;
    }
}
