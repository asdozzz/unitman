<?php

namespace App\Unitman\Infra\Temporal\Activity;

use App\Unitman\Business\Command\Unit\UstanovitResultatObnovleniyaUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatOstanovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatPodgotovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatSborkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatSbrosaPodgotovki;
use App\Unitman\Business\Command\Unit\UstanovitResultatSbrosaPodgotovkiOtRunnera;
use App\Unitman\Business\Command\Unit\UstanovitResultatUdaleniya;
use App\Unitman\Business\Command\Unit\UstanovitResultatZapuska;
use App\Unitman\Business\ReadModel\Unit\OcheredUnitovReadModel;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatObnovleniyaUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatOstanovkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatPodgotovkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatSborkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatSbrosaPodgotovkiOtRunneraUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatSbrosaPodgotovkiUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatUdaleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatZapuskaUseCase;
use App\Unitman\Infra\Repository\Unit\OcheredUnitovRepository;

final class OcheredUnitovActivity
{
    public function __construct(
        private OcheredUnitovRepository $ocheredUnitovRepository,
        private UstanovitResultatSborkiUnitaUseCase $ustanovitResultatSborkiUnitaUseCase,
        private UstanovitResultatObnovleniyaUnitaUseCase $ustanovitResultatObnovleniyaUnitaUseCase,
        private UstanovitResultatPodgotovkiUnitaUseCase $ustanovitResultatPodgotovkiUnitaUseCase,
        private UstanovitResultatSbrosaPodgotovkiUseCase $ustanovitResultatSbrosaPodgotovkiUseCase,
        private UstanovitResultatZapuskaUseCase $ustanovitResultatZapuskaUseCase,
        private UstanovitResultatOstanovkiUnitaUseCase $ustanovitResultatOstanovkiUnitaUseCase,
        private UstanovitResultatUdaleniyaUseCase $ustanovitResultatUdaleniyaUseCase,
    )
    {
    }

    /**
     * @return OcheredUnitovReadModel[]
     * */
    public function poluchitZadachiNaObrabotku(int $limit = 10): array
    {
        return $this->ocheredUnitovRepository->poluchitZadachiNaObrabotku($limit);
    }

    public function obrabotatZadachu(OcheredUnitovReadModel $model): bool
    {
        $result = match ($model->queueName) {
            OcheredUnitovReadModel::SBORKA => $this->sborka($model),
            OcheredUnitovReadModel::OBNOVLENIE => $this->obnovlenie($model),
            OcheredUnitovReadModel::PODGOTOVKA => $this->podgotovka($model),
            OcheredUnitovReadModel::SBROS_PODGOTOVKI => $this->sbrosPodgotovki($model),
            OcheredUnitovReadModel::ZAPUSK => $this->zapusk($model),
            OcheredUnitovReadModel::OSTANOVKA => $this->ostanovka($model),
            OcheredUnitovReadModel::UDALENIE => $this->udalenie($model),
        };

        return $result;
    }

    private function sborka(OcheredUnitovReadModel $model): bool
    {
        $this->ustanovitResultatSborkiUnitaUseCase->handle(new UstanovitResultatSborkiUnita($model->unitId));
        return true;
    }

    private function obnovlenie(OcheredUnitovReadModel $model): bool
    {
        $this->ustanovitResultatObnovleniyaUnitaUseCase->handle(new UstanovitResultatObnovleniyaUnita($model->unitId));
        return true;
    }

    private function podgotovka(OcheredUnitovReadModel $model): bool
    {
        $this->ustanovitResultatPodgotovkiUnitaUseCase->handle(new UstanovitResultatPodgotovkiUnita($model->unitId));
        return true;
    }

    private function sbrosPodgotovki(OcheredUnitovReadModel $model): bool
    {
        $this->ustanovitResultatSbrosaPodgotovkiUseCase->handle(new UstanovitResultatSbrosaPodgotovki($model->unitId));
        return true;
    }

    private function zapusk(OcheredUnitovReadModel $model): bool
    {
        $this->ustanovitResultatZapuskaUseCase->handle(new UstanovitResultatZapuska($model->unitId));
        return true;
    }

    private function ostanovka(OcheredUnitovReadModel $model): bool
    {
        $this->ustanovitResultatOstanovkiUnitaUseCase->handle(new UstanovitResultatOstanovkiUnita($model->unitId));
        return true;
    }

    private function udalenie(OcheredUnitovReadModel $model): bool
    {
        $this->ustanovitResultatUdaleniyaUseCase->handle(new UstanovitResultatUdaleniya($model->unitId));
        return true;
    }
}
