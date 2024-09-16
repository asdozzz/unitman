<?php

namespace App\Unitman\Infra\Jobs;

use App\Unitman\Business\Command\Unit\UstanovitResultatIzmenenniyaVetkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatObnovleniyaUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatOstanovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatPodgotovkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatSborkiUnita;
use App\Unitman\Business\Command\Unit\UstanovitResultatSbrosaPodgotovki;
use App\Unitman\Business\Command\Unit\UstanovitResultatUdaleniya;
use App\Unitman\Business\Command\Unit\UstanovitResultatZapuska;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\Unit\OcheredUnitovReadModel;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatIzmeneniyaVetkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatObnovleniyaUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatOstanovkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatPodgotovkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatSborkiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatSbrosaPodgotovkiUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatUdaleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\UstanovitResultatZapuskaUseCase;
use FluffyDiscord\RoadRunnerBundle\Worker\JobsWorker\JobsHandlerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class OcheredUnitovJobHandler implements JobsHandlerInterface
{
    const QUEUE_NAME = 'ochered_unitov';

    public function __construct(
        private SerializerInterface $serializer,
        private UnitRepository $unitRepository,
        private UstanovitResultatSborkiUnitaUseCase $ustanovitResultatSborkiUnitaUseCase,
        private UstanovitResultatObnovleniyaUnitaUseCase $ustanovitResultatObnovleniyaUnitaUseCase,
        private UstanovitResultatPodgotovkiUnitaUseCase $ustanovitResultatPodgotovkiUnitaUseCase,
        private UstanovitResultatSbrosaPodgotovkiUseCase $ustanovitResultatSbrosaPodgotovkiUseCase,
        private UstanovitResultatZapuskaUseCase $ustanovitResultatZapuskaUseCase,
        private UstanovitResultatOstanovkiUnitaUseCase $ustanovitResultatOstanovkiUnitaUseCase,
        private UstanovitResultatUdaleniyaUseCase $ustanovitResultatUdaleniyaUseCase,
        private UstanovitResultatIzmeneniyaVetkiUnitaUseCase $ustanovitResultatIzmeneniyaVetkiUnitaUseCase
    )
    {
    }


    public function isSupported(ReceivedTaskInterface $task): bool
    {
        return $task->getPipeline() === self::QUEUE_NAME;
    }

    public function handle(ReceivedTaskInterface $task): void
    {
        $model = $this->serializer->deserialize($task->getPayload(), $task->getName(), 'json');

        try {
            /** @var OcheredUnitovReadModel $model*/
            match ($model->type) {
                OcheredUnitovReadModel::SBORKA => $this->sborka($model),
                OcheredUnitovReadModel::OBNOVLENIE => $this->obnovlenie($model),
                OcheredUnitovReadModel::PODGOTOVKA => $this->podgotovka($model),
                OcheredUnitovReadModel::SBROS_PODGOTOVKI => $this->sbrosPodgotovki($model),
                OcheredUnitovReadModel::ZAPUSK => $this->zapusk($model),
                OcheredUnitovReadModel::OSTANOVKA => $this->ostanovka($model),
                OcheredUnitovReadModel::UDALENIE => $this->udalenie($model),
                OcheredUnitovReadModel::IZMENENIYE_VETKI => $this->izmenitVetku($model),
            };
        } catch (\Exception $e) {
            $task->withHeader('attempts', 2);
            throw $e;
        }

    }

    private function sborka(OcheredUnitovReadModel $model): bool
    {
        $unit = $this->unitRepository->getById($model->unitId);
        if ($unit->esliJdetResultatSborki()) {
            $this->ustanovitResultatSborkiUnitaUseCase->handle(new UstanovitResultatSborkiUnita($model->unitId));
        }

        return true;
    }

    private function obnovlenie(OcheredUnitovReadModel $model): bool
    {
        $unit = $this->unitRepository->getById($model->unitId);
        if ($unit->esliJdetResultatObnovleniya()) {
            $this->ustanovitResultatObnovleniyaUnitaUseCase->handle(new UstanovitResultatObnovleniyaUnita($model->unitId));
        }

        return true;
    }

    private function podgotovka(OcheredUnitovReadModel $model): bool
    {
        $unit = $this->unitRepository->getById($model->unitId);
        if ($unit->esliJdetResultatPodgotovki()) {
            $this->ustanovitResultatPodgotovkiUnitaUseCase->handle(new UstanovitResultatPodgotovkiUnita($model->unitId));
        }

        return true;
    }

    private function sbrosPodgotovki(OcheredUnitovReadModel $model): bool
    {
        $unit = $this->unitRepository->getById($model->unitId);
        if ($unit->esliJdetResultatSbrosaPodgotovki()) {
            $this->ustanovitResultatSbrosaPodgotovkiUseCase->handle(new UstanovitResultatSbrosaPodgotovki($model->unitId));
        }

        return true;
    }

    private function zapusk(OcheredUnitovReadModel $model): bool
    {
        $unit = $this->unitRepository->getById($model->unitId);
        if ($unit->esliJdetResultatZapuska()) {
            $this->ustanovitResultatZapuskaUseCase->handle(new UstanovitResultatZapuska($model->unitId));
        }

        return true;
    }

    private function ostanovka(OcheredUnitovReadModel $model): bool
    {
        $unit = $this->unitRepository->getById($model->unitId);
        if ($unit->esliJdetResultatOstanovki()) {
            $this->ustanovitResultatOstanovkiUnitaUseCase->handle(new UstanovitResultatOstanovkiUnita($model->unitId));
        }

        return true;
    }

    private function udalenie(OcheredUnitovReadModel $model): bool
    {
        $unit = $this->unitRepository->getById($model->unitId);
        if ($unit->esliJdetResultatUdaleniya()) {
            $this->ustanovitResultatUdaleniyaUseCase->handle(new UstanovitResultatUdaleniya($model->unitId));
        }

        return true;
    }

    private function izmenitVetku(OcheredUnitovReadModel $model): bool
    {
        $unit = $this->unitRepository->getById($model->unitId);
        if ($unit->esliJdetResultatIzmeneniyaVetki()) {
            $this->ustanovitResultatIzmeneniyaVetkiUnitaUseCase->handle(new UstanovitResultatIzmenenniyaVetkiUnita($model->unitId));
        }

        return true;
    }
}
