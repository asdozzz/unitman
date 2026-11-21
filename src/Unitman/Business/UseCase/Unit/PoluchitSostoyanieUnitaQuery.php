<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitSostoyanieUnita;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\Unit\SostoyanieUnitaReadModel;
use App\Unitman\Business\ReadModel\Unit\ZanyatostUnitaReadModel;

final class PoluchitSostoyanieUnitaQuery
{
    public function __construct(
        private UnitRepository $unitRepository,
        private ProjectRepository $projectRepository,
        private PoluchitZanyatostUnitaQuery $poluchitZanyatostUnitaQuery,
    )
    {
    }

    /**
     * Чтение состояния юнита для внешних модулей. Любой отказ агрегата
     * (например, юнит удалён или не найден) трактуется как «юнита нет»:
     * интеграция должна уметь работать с отсутствующим юнитом, а не падать.
     */
    function handle(PoluchitSostoyanieUnita $command): SostoyanieUnitaReadModel
    {
        $unitId = trim($command->unitId);

        if ($unitId === '') {
            return SostoyanieUnitaReadModel::otsutstvuet();
        }

        try {
            $unit = $this->unitRepository->getById($unitId);
        } catch (\DomainException) {
            return SostoyanieUnitaReadModel::otsutstvuet();
        }

        return $this->izUnita($unit);
    }

    private function izUnita(Unit $unit): SostoyanieUnitaReadModel
    {
        $code = $unit->poluchitKodSostoyaniya();

        if ($code === null) {
            return SostoyanieUnitaReadModel::otsutstvuet();
        }

        $zanyatost = $this->poluchitZanyatostUnitaQuery->dlyaUnita($unit);
        $vetkaZashishena = $this->vetkaZashishena($unit);

        return SostoyanieUnitaReadModel::izUnita(
            $code,
            $unit->getProjectId(),
            $unit->getName(),
            $unit->getBranch(),
            $unit->getAuthorId(),
            $zanyatost->zanyat(),
            $this->prichinaZanyatosti($zanyatost),
            $unit->getRedaktirovanieVklucheno(),
            $vetkaZashishena,
            $unit->getRedaktory(),
        );
    }

    /**
     * Защищённые ветки не денормализуются в юнит: сверка идёт по проекту.
     * Недоступный проект трактуется как «ветка не защищена».
     */
    private function vetkaZashishena(Unit $unit): bool
    {
        try {
            $project = $this->projectRepository->getById($unit->getProjectId());
        } catch (\DomainException) {
            return false;
        }

        return $project->soderzhitZashishennuyuVetku($unit->getBranch());
    }

    /**
     * Причина нужна только для занятого юнита: у свободного её нет,
     * иначе интерфейс начал бы показывать «причину» без самой блокировки.
     */
    private function prichinaZanyatosti(ZanyatostUnitaReadModel $zanyatost): ?string
    {
        return $zanyatost->zanyat() ? $zanyatost->prichina() : null;
    }
}
