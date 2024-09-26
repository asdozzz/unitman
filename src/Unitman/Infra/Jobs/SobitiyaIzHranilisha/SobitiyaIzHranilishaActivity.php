<?php

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha;

use App\Unitman\Business\Command\Unit\ObnovitKodUnita;
use App\Unitman\Business\Command\Unit\ObnovitKodUnitaPosleZapuska;
use App\Unitman\Business\Command\Unit\UdalitUnit;
use App\Unitman\Business\Command\Unit\UdalitUnitPosleZapuska;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\SobitieIzHranilisha;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\UseCase\Unit\ObnovitKodUnitaPosleZapuskaUseCase;
use App\Unitman\Business\UseCase\Unit\ObnovitKodUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\SozdatUnitSystemoiUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitUnitPosleZapuskaUseCase;
use App\Unitman\Business\UseCase\Unit\UdalitUnitUseCase;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter\StorageTypeAdapterFactory;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;

//TODO декомпозировать класс
final class SobitiyaIzHranilishaActivity
{
    const OK = 'ok';
    const EMPTY_IDS = 'empty ids';

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
        private SozdatUnitSystemoiUseCase $sozdatUnitSystemoiUseCase
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
            TipSobitiya::KOD_OBNOVLEN => $this->obnovitUnixtimePoslednegoIzmeneniya($project, $dannieSobitiya),
            TipSobitiya::VETKA_UDALENA => $this->udalitUniti($project, $dannieSobitiya),
            TipSobitiya::VETKA_SOZDANA => $this->sozdatUnit($project, $dannieSobitiya)
        };

        return $result;
    }

    private function sozdatUnit(Project $project, DannieSobitiya $dannieSobitiya): string
    {
        if (!$project->poluchitNastroikiHuka()->avtosozdanie) {
            return 'avtosozdanie_viklucheno';
        }

        try {
            $this->sozdatUnitSystemoiUseCase->handle($project->getId(), $dannieSobitiya->vetka);
        } catch (\Exception $e) {
            return $e->getMessage();
        }


        return self::OK;
    }

    private function obnovitUnixtimePoslednegoIzmeneniya(Project $project, DannieSobitiya $dannieSobitiya): string
    {
        $ids = $this->spisokUnitovRepository->findIdsByBranch($project->getId(), $dannieSobitiya->vetka);

        if (empty($ids)) {
            return self::EMPTY_IDS;
        }

        $errs = [];
        foreach ($ids as $id) {
            try {
                $unit = $this->unitRepository->getById($id);
                $unit->izmenitVremyaPoslednegoIzmeneniyaKodaVHranilishe($dannieSobitiya->unixtime);
                $this->unitRepository->save($unit);

                if (!$project->poluchitNastroikiHuka()->avtoobnovlenie) {
                    continue;
                }

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

        return self::OK;
    }

    private function udalitUniti(Project $project, DannieSobitiya $dannieSobitiya): string
    {
        if (!$project->poluchitNastroikiHuka()->avtoudalenie) {
            return self::OK;
        }
        $ids = $this->spisokUnitovRepository->findIdsByBranch($project->getId(), $dannieSobitiya->vetka);

        if (empty($ids)) {
            return self::EMPTY_IDS;
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

        return self::OK;
    }
}
