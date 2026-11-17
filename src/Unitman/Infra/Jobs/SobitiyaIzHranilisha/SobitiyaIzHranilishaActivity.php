<?php

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\SobitieIzHranilisha;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\UseCase\Unit\DobavitProzesObnovleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\DobavitProzesUdaleniyaUseCase;
use App\Unitman\Business\UseCase\Unit\OtlozhitDeistvieNadUnitomUseCase;
use App\Unitman\Business\UseCase\Unit\SozdatUnitSystemoiUseCase;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter\StorageTypeAdapterFactory;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;

//TODO декомпозировать класс
final class SobitiyaIzHranilishaActivity
{
    const OK = 'ok';
    const EMPTY_IDS = 'empty ids';

    /**
     * Событие принято, но применить его сразу нельзя: юнит занят ходом
     * OpenCode, намерение сохранено и будет применено автоматически.
     */
    const DEFERRED = 'deferred';

    public function __construct(
        private StorageTypeAdapterFactory $storageTypeAdapterFactory,
        private ProjectRepository $projectRepository,
        private RepoRepository $repoRepository,
        private SpisokUnitovRepository $spisokUnitovRepository,
        private DobavitProzesObnovleniyaUseCase $dobavitProzesObnovleniyaUseCase,
        private DobavitProzesUdaleniyaUseCase $dobavitProzesUdaleniyaUseCase,
        private UnitRepository $unitRepository,
        private SozdatUnitSystemoiUseCase $sozdatUnitSystemoiUseCase,
        private ProverkaSvobodyUnita $proverkaSvobody,
        private OtlozhitDeistvieNadUnitomUseCase $otlozhitDeistvieNadUnitomUseCase,
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
        $otlozheno = 0;
        foreach ($ids as $id) {
            try {
                $unit = $this->unitRepository->getById($id);
                /*
                 * Метка времени обновляется немедленно всегда: это событие
                 * event store, раннер в нём не участвует, и занятость юнита
                 * ему не мешает.
                 */
                $unit->izmenitVremyaPoslednegoIzmeneniyaKodaVHranilishe($dannieSobitiya->unixtime);
                $this->unitRepository->save($unit);

                if (!$project->poluchitNastroikiHuka()->avtoobnovlenie) {
                    continue;
                }

                if (!$unit->esliNugnoObnovitKodUnita()) {
                    $errs[] = 'ne nugno obnovlyat';

                    continue;
                }

                /*
                 * Несобранный юнит не откладываем: такое состояние не
                 * транзиентное (сам он не соберётся), и намерение висело бы
                 * вечно, пока юнит не удалят. Сохраняем прежнюю ошибку.
                 */
                if (!$unit->esliSobran()) {
                    $errs[] = 'invalid status';

                    continue;
                }

                /*
                 * Проверка свободы выполняется явно: `handleSystem` передаёт
                 * `neproveryatProzesi: true`, и агрегат не остановит запуск
                 * обновления. Событие не теряем — откладываем до освобождения.
                 */
                if (!$this->proverkaSvobody->svoboden($unit)) {
                    $this->otlozhitDeistvieNadUnitomUseCase->handle(
                        $unit,
                        TipSobitiya::KOD_OBNOVLEN,
                        $dannieSobitiya
                    );
                    $otlozheno++;

                    continue;
                }

                $this->dobavitProzesObnovleniyaUseCase->handleSystem($id);
            } catch (\Exception $e) {
                $errs[] = $e->getMessage();
            }
        }

        if (!empty($errs)) {
            return join(",", $errs);
        }

        return $otlozheno > 0 ? self::DEFERRED : self::OK;
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
        $otlozheno = 0;
        foreach ($ids as $id) {
            try {
                $unit = $this->unitRepository->getById($id);

                /*
                 * Удаление занятого юнита откладываем: идущий поток OpenCode
                 * продолжает писать в юнит, и удаление оборвало бы его на
                 * середине. Проверка явная по той же причине, что и для
                 * обновления: `handleSystem` отключает проверки агрегата.
                 */
                if (!$this->proverkaSvobody->svoboden($unit)) {
                    $this->otlozhitDeistvieNadUnitomUseCase->handle(
                        $unit,
                        TipSobitiya::VETKA_UDALENA,
                        $dannieSobitiya
                    );
                    $otlozheno++;

                    continue;
                }

                $this->dobavitProzesUdaleniyaUseCase->handleSystem($id);
            } catch (\Exception $e) {
                $errs[] = $e->getMessage();
            }
        }

        if (!empty($errs)) {
            return join(",", $errs);
        }

        return $otlozheno > 0 ? self::DEFERRED : self::OK;
    }
}
