<?php

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha;

use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Command\Unit\ObnovitKodUnitaPosleZapuska;
use App\Unitman\Business\Command\Unit\UdalitUnit;
use App\Unitman\Business\Command\Unit\UdalitUnitPosleZapuska;
use App\Unitman\Business\Model\SobitieIzHranilisha;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\UseCase\Unit\ObnovitKodUnitaPosleZapuskaUseCase;
use App\Unitman\Business\UseCase\Unit\ObnovitKodUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitUnitPosleZapuskaUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitUnitUseCase;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter\StorageTypeAdapterFactory;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;

//TODO декомпозировать класс
final class SobitiyaIzHranilishaActivity
{
    public function __construct(
        private StorageTypeAdapterFactory $storageTypeAdapterFactory,
        private ProjectRepository $projectRepository,
        private RepoRepository $repoRepository,
        private SpisokUnitovRepository $spisokUnitovRepository,
        private UnitRepository $unitRepository,
        private UdalitUnitUseCase $udalitUnitUseCase,
        private UdalitUnitPosleZapuskaUseCase $udalitUnitPosleZapuskaUseCase,
        private ObnovitKodUnitaPosleZapuskaUseCase $obnovitKodUnitaPosleZapuskaUseCase,
        private ObnovitKodUnitaUseCase $obnovitKodUnitaUseCase,
    )
    {
    }

    function obrabotatZadachu(SobitieIzHranilisha $model): string
    {
        $project = $this->projectRepository->getById($model->projectId);
        $repo = $this->repoRepository->getById($project->getRepoId());
        $adapter = $this->storageTypeAdapterFactory->getAdapter($repo->getType());

        if (!$adapter->esliValidnoeSobitie($model)) {
            return 'invalid';
        }

        $dannieSobitiya = $adapter->poluchitDannieSobitiya($model);

        $result = match ($dannieSobitiya->tipSobitiya) {
            TipSobitiya::KOD_OBNOVLEN => $this->obnovitUnixtimePoslednegoIzmeneniya($model->projectId, $dannieSobitiya),
            TipSobitiya::VETKA_UDALENA => $this->udalitUniti($model->projectId, $dannieSobitiya)
        };

        return $result;
    }

    private function obnovitUnixtimePoslednegoIzmeneniya(string $projectId, DannieSobitiya $dannieSobitiya): string
    {
        $ids = $this->spisokUnitovRepository->findIdsByBranch($projectId, $dannieSobitiya->vetka);

        if (empty($ids)) {
            return 'empty ids';
        }

        $errs = [];
        foreach ($ids as $id) {
            try {
                $unit = $this->unitRepository->getById($id);
                $unit->izmenitVremyaPoslednegoIzmeneniyaKodaVHranilishe($dannieSobitiya->unixtime);
                $this->unitRepository->save($unit);

                if ($unit->esliNugnoObnovitKodUnita()) {
                    if ($unit->esliZapushen()) {
                        $this->obnovitKodUnitaPosleZapuskaUseCase->handleSystem(new ObnovitKodUnitaPosleZapuska($id));
                    } else if ($unit->esliMognoObnovit()) {
                        $this->obnovitKodUnitaUseCase->handleSystem(new ObnovitKodUnita($id));
                    } else {
                        $errs[] = 'invalid status';
                    }
                } else {
                    $errs[] = 'ne nugno obnovlyat';
                }
            } catch (\Exception $e) {
                $errs[] = $e->getMessage();
            }
        }

        if (!empty($errs)) {
            return join(",", $errs);
        }

        return 'ok';
    }

    private function udalitUniti(string $projectId, DannieSobitiya $dannieSobitiya): string
    {
        $ids = $this->spisokUnitovRepository->findIdsByBranch($projectId, $dannieSobitiya->vetka);

        if (empty($ids)) {
            return 'empty ids';
        }

        $errs = [];
        foreach ($ids as $id) {
            try {
                $unit = $this->unitRepository->getById($id);

                if ($unit->esliZapushen()) {
                    $this->udalitUnitPosleZapuskaUseCase->handleSystem(new UdalitUnitPosleZapuska($id));
                } else {
                    $this->udalitUnitUseCase->handleSystem(new UdalitUnit($id));
                }
            } catch (\Exception $e) {
                $errs[] = $e->getMessage();
            }
        }

        if (!empty($errs)) {
            return join(",", $errs);
        }

        return 'ok';
    }
}
