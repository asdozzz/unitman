<?php

namespace App\Unitman\Infra\BackgroundJob\OcheredUnitov;

use App\Unitman\Business\ReadModel\Unit\OcheredUnitovReadModel;
use App\Unitman\Business\UseCase\Unit\ObrabotatProzesiUnitaUseCase;

use App\Unitman\Infra\Repository\Unit\OcheredUnitovRepository;

final class OcheredUnitovActivity
{
    public function __construct(
        private OcheredUnitovRepository $ocheredUnitovRepository,
        private ObrabotatProzesiUnitaUseCase $obrabotatProzesiUnitaUseCase
    )
    {
    }

    /**
     * @return OcheredUnitovReadModel[]
     * */
    public function poluchitZadachiNaObrabotku(int $limit = 10): array
    {
        $this->ocheredUnitovRepository->init();
        return $this->ocheredUnitovRepository->poluchitZadachiNaObrabotku($limit);
    }

    public function obrabotatZadachu(OcheredUnitovReadModel $model): void
    {
        $this->obrabotatProzesiUnitaUseCase->handle($model->unitId);
    }
}
