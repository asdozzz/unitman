<?php

namespace App\Unitman\Infra\Jobs\SobitiyaIzHranilisha;

use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\SobitieIzHranilisha;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\UseCase\Unit\ObrabotatSostoyanieVetkiUseCase;
use App\Unitman\Business\UseCase\Unit\SozdatUnitSystemoiUseCase;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\SobitiePayloadDecoder;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\SobitiePayloadException;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter\StorageTypeAdapterFactory;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use Psr\Log\LoggerInterface;

//TODO декомпозировать класс
final class SobitiyaIzHranilishaActivity
{
    const OK = 'ok';
    const EMPTY_IDS = 'empty ids';

    /**
     * Слияние произошло не в основную ветку проекта: юнит удалять не нужно.
     */
    const NOT_MAIN_BRANCH = 'not_main_branch';

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
        private UnitRepository $unitRepository,
        private SozdatUnitSystemoiUseCase $sozdatUnitSystemoiUseCase,
        private ObrabotatSostoyanieVetkiUseCase $obrabotatSostoyanieVetki,
        private SobitiePayloadDecoder $payloadDecoder,
        private LoggerInterface $logger,
    )
    {
    }

    function obrabotatZadachu(SobitieIzHranilisha $model): string
    {
        $project = $this->projectRepository->getById($model->projectId);
        $repo = $this->repoRepository->getById($project->getRepoId());
        $adapter = $this->storageTypeAdapterFactory->getAdapter($repo->getType());

        /*
         * Тело вебхука разбирается ровно один раз и передаётся адаптеру готовым
         * объектом: раньше `json_decode` выполнялся и в `esliValidnoeSobitie`,
         * и в `poluchitDannieSobitiya`. Ошибка разбора не пробрасывается выше —
         * иначе задание RoadRunner ушло бы в retry-цикл.
         */
        try {
            $payload = $this->payloadDecoder->decode($model->eventPayload);
        } catch (SobitiePayloadException $e) {
            $this->logger->warning($this->klyuchDlyaLoga($e->getMessage()), [
                'sobitieId' => $model->id,
                'projectId' => $model->projectId,
                'payloadBytes' => strlen($model->eventPayload),
                'limitBytes' => SobitiePayloadDecoder::MAX_EVENT_PAYLOAD_BYTES,
            ]);

            return $e->getMessage();
        }

        if (!$adapter->esliValidnoeSobitie($payload)) {
            return 'invalid';
        }

        $dannieSobitiya = $adapter->poluchitDannieSobitiya($payload, $project->getMainBranchName());

        /*
         * Валидное событие доказывает, что Git-вебхук настроен. С этого
         * момента polling для проекта выключается, а флаг остаётся видимым
         * в настройках.
         */
        if (!$project->poluchitNastroikiHuka()->vebhukNastroen) {
            $project->otmetitVebhukNastroennim();
            $this->projectRepository->save($project);
        }

        $result = match ($dannieSobitiya->tipSobitiya) {
            TipSobitiya::KOD_OBNOVLEN => $this->obnovitUnixtimePoslednegoIzmeneniya($project, $dannieSobitiya),
            TipSobitiya::VETKA_UDALENA => $this->udalitUniti($project, $dannieSobitiya),
            TipSobitiya::VETKA_SOZDANA => $this->sozdatUnit($project, $dannieSobitiya),
            TipSobitiya::VETKA_SMERZHENA => $this->obrabotatMerzh($project, $dannieSobitiya),
        };

        return $result;
    }

    /**
     * Переводит стабильный код ошибки декодера в ключ словаря для лога.
     */
    private function klyuchDlyaLoga(string $kod): string
    {
        return match ($kod) {
            SobitiePayloadDecoder::ERROR_TOO_LARGE => 'sobitie_iz_hranilisha.payload_too_large',
            default => 'sobitie_iz_hranilisha.payload_malformed',
        };
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
                $result = $this->obrabotatSostoyanieVetki->handle(
                    $this->unitRepository->getById($id),
                    TipSobitiya::KOD_OBNOVLEN,
                    $dannieSobitiya,
                );

                if ($result === ObrabotatSostoyanieVetkiUseCase::DEFERRED
                    || $result === ObrabotatSostoyanieVetkiUseCase::HELD
                ) {
                    $otlozheno++;

                    continue;
                }

                if (!in_array($result, [
                    ObrabotatSostoyanieVetkiUseCase::OK,
                    ObrabotatSostoyanieVetkiUseCase::NOT_NEEDED,
                    ObrabotatSostoyanieVetkiUseCase::PENDING_DELETE,
                ], true)) {
                    $errs[] = $result;
                }
            } catch (\Exception $e) {
                $errs[] = $e->getMessage();
            }
        }

        if (!empty($errs)) {
            return join(",", $errs);
        }

        return $otlozheno > 0 ? self::DEFERRED : self::OK;
    }

    /**
     * Слияние ветки в основную ветку проекта трактуем как завершение работы
     * над юнитом: ветка может остаться в репозитории, но юнит больше не нужен.
     * Поведение совпадает с удалением ветки, включая флаг `avtoudalenie` и
     * откладывание, если юнит занят.
     */
    private function obrabotatMerzh(Project $project, DannieSobitiya $dannieSobitiya): string
    {
        if ($dannieSobitiya->celVetka !== null
            && $dannieSobitiya->celVetka !== $project->getMainBranchName()
        ) {
            return self::NOT_MAIN_BRANCH;
        }

        return $this->udalitUniti($project, $dannieSobitiya);
    }

    private function udalitUniti(Project $project, DannieSobitiya $dannieSobitiya): string
    {
        $ids = $this->spisokUnitovRepository->findIdsByBranch($project->getId(), $dannieSobitiya->vetka);

        if (empty($ids)) {
            return self::EMPTY_IDS;
        }

        $errs = [];
        $otlozheno = 0;
        foreach ($ids as $id) {
            try {
                $result = $this->obrabotatSostoyanieVetki->handle(
                    $this->unitRepository->getById($id),
                    $dannieSobitiya->tipSobitiya,
                    $dannieSobitiya,
                );

                if ($result === ObrabotatSostoyanieVetkiUseCase::DEFERRED) {
                    $otlozheno++;

                    continue;
                }

                if ($result !== ObrabotatSostoyanieVetkiUseCase::OK) {
                    $errs[] = $result;
                }
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
