<?php

namespace App\Unitman\Infra\BackgroundJob\OcheredProektov;

use App\Unitman\Business\Command\Unit\UstanovitResultatUdaleniya;
use App\Unitman\Business\ReadModel\Project\OcheredProektovReadModel;
use App\Unitman\Business\UseCase\Project\UstanovitResultatOchistkiProekta;
use App\Unitman\Business\UseCase\Project\UstanovitResultatSborkiProektaUseCase;
use App\Unitman\Business\UseCase\Project\UstanovitResultatUdaleniyaProektaUseCase;
use App\Unitman\Infra\Repository\Project\OcheredProektovRepository;

final class OcheredProektovActivity
{
    public function __construct(
        private UstanovitResultatSborkiProektaUseCase $ustanovitResultatSborkiProektaUseCase,
        private UstanovitResultatUdaleniyaProektaUseCase $ustanovitResultatUdaleniyaProektaUseCase,
        private UstanovitResultatOchistkiProekta $ustanovitResultatOchistkiProekta,
        private OcheredProektovRepository $ocheredProektovRepository
    )
    {
    }

    /**
     * @return OcheredProektovReadModel[]
     * */
    public function poluchitZadachiNaObrabotku(int $limit = 10): array
    {
        $this->ocheredProektovRepository->init();
        return $this->ocheredProektovRepository->poluchitZadachiNaObrabotku($limit);
    }

    public function obrabotatZadachu(OcheredProektovReadModel $model): bool
    {
        $result = match ($model->queueName) {
            OcheredProektovReadModel::SBORKA_PROEKTA => $this->sborka($model),
            OcheredProektovReadModel::UDALENIE_PROEKTA => $this->udalenie($model),
            OcheredProektovReadModel::OCHISTKA_PROEKTA => $this->ochistka($model),
        };

        return $result;
    }

    private function sborka(OcheredProektovReadModel $model): bool
    {
        $this->ustanovitResultatSborkiProektaUseCase->handle($model->projectId);
        return true;
    }

    private function udalenie(OcheredProektovReadModel $model): bool
    {
        $this->ustanovitResultatUdaleniyaProektaUseCase->handle($model->projectId);
        return true;
    }

    private function ochistka(OcheredProektovReadModel $model): bool
    {
        $this->ustanovitResultatOchistkiProekta->handle($model->projectId);
        return true;
    }
}
