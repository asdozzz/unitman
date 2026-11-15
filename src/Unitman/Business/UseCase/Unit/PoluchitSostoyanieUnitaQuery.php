<?php

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitSostoyanieUnita;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\Unit\SostoyanieUnitaReadModel;

final class PoluchitSostoyanieUnitaQuery
{
    public function __construct(private UnitRepository $unitRepository)
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

        return SostoyanieUnitaReadModel::izUnita(
            $code,
            $unit->getProjectId(),
            $unit->getName(),
            $unit->getBranch(),
            $unit->getAuthorId(),
        );
    }
}
