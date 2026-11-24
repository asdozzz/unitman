<?php

namespace App\Unitman\Infra\BackgroundJob\OcheredProektov;

use App\Unitman\Business\ReadModel\Project\OcheredProektovReadModel;
use App\Unitman\Business\UseCase\Project\UstanovitResultatUdaleniyaProektaUseCase;
use App\Unitman\Infra\Repository\Project\OcheredProektovRepository;

final class OcheredProektovActivity
{
    public function __construct(
        private UstanovitResultatUdaleniyaProektaUseCase $ustanovitResultatUdaleniyaProektaUseCase,
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
            OcheredProektovReadModel::UDALENIE_PROEKTA => $this->udalenie($model),
        };

        return $result;
    }

    private function udalenie(OcheredProektovReadModel $model): bool
    {
        $this->ustanovitResultatUdaleniyaProektaUseCase->handle($model->projectId);
        return true;
    }
}
