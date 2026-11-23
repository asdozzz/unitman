<?php

declare(strict_types=1);

namespace App\Unitman\Infra\BackgroundJob\VetkiBezVebhuka;

use App\Unitman\Business\Model\Project\StatusVetki;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Business\Port\Project\UmeetPoluchatSostoyanieVetki;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Unit\UmeetPoluchatUnitiDlyaOprosaVetok;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\SostoyanieVetki;
use App\Unitman\Business\ReadModel\Unit\UnitaDlyaOprosaVetok;
use App\Unitman\Business\UseCase\Unit\ObrabotatSostoyanieVetkiUseCase;
use Psr\Log\LoggerInterface;

/**
 * Polling-замена Git-вебхука для проектов, где он ещё не подтверждён.
 *
 * Ветка одного проекта запрашивается один раз на проход: юниты часто делят
 * ветку, и без кэша polling быстро упёрся бы в лимиты Git-провайдера.
 */
final class OprosVetokBezVebhukaActivity
{
    public function __construct(
        private UmeetPoluchatUnitiDlyaOprosaVetok $unitiDlyaOprosa,
        private RepoRepository $repoRepository,
        private UmeetPoluchatSostoyanieVetki $storage,
        private UnitRepository $unitRepository,
        private ObrabotatSostoyanieVetkiUseCase $obrabotatSostoyanie,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return int число юнитов, по которым было принято решение
     */
    public function obrabotat(): int
    {
        $uniti = $this->unitiDlyaOprosa->poluchitUnitiDlyaOprosaVetok();

        if ($uniti === []) {
            return 0;
        }

        $poProektam = [];
        foreach ($uniti as $unit) {
            $poProektam[$unit->projectId][] = $unit;
        }

        $obrabotano = 0;

        foreach ($poProektam as $projectId => $projectUnits) {
            try {
                $repo = $this->repoRepository->getById($projectUnits[0]->repoId);
            } catch (\Throwable $e) {
                $this->logger->error('unit.polling_vetok.repo_ne_najden', [
                    'projectId' => $projectId,
                    'exception' => $e,
                ]);

                continue;
            }

            $kesh = [];
            foreach ($projectUnits as $unit) {
                $sostoyanie = $kesh[$unit->branch] ??= $this->poluchitSostoyanie($repo, $unit);

                if ($sostoyanie === null || $sostoyanie->status === StatusVetki::UNKNOWN) {
                    continue;
                }

                if ($this->obrabotatUnit($unit, $sostoyanie)) {
                    $obrabotano++;
                }
            }
        }

        return $obrabotano;
    }

    private function poluchitSostoyanie(Repo $repo, UnitaDlyaOprosaVetok $unit): ?SostoyanieVetki
    {
        try {
            return $this->storage->poluchitSostoyanieVetki(
                $repo,
                $unit->projectCode,
                $unit->branch,
                $unit->mainBranch,
            );
        } catch (\Throwable $e) {
            $this->logger->warning('unit.polling_vetok.vetka_ne_proverena', [
                'projectId' => $unit->projectId,
                'branch' => $unit->branch,
                'exception' => $e,
            ]);

            return null;
        }
    }

    private function obrabotatUnit(UnitaDlyaOprosaVetok $unit, SostoyanieVetki $sostoyanie): bool
    {
        $tipSobitiya = match ($sostoyanie->status) {
            StatusVetki::ACTIVE => TipSobitiya::KOD_OBNOVLEN,
            StatusVetki::MERGED => TipSobitiya::VETKA_SMERZHENA,
            StatusVetki::DELETED => TipSobitiya::VETKA_UDALENA,
            StatusVetki::UNKNOWN => null,
        };

        if ($tipSobitiya === null) {
            return false;
        }

        if ($sostoyanie->status === StatusVetki::ACTIVE) {
            if ($sostoyanie->posledniyKommitUnixtime === null) {
                return false;
            }

            /*
             * Первый проход только фиксирует базовый HEAD. Иначе каждый юнит,
             * созданный до включения polling, получил бы ложное обновление.
             */
            if ($unit->unixtimePoslednegoObnovleniyaVHranilishe === null) {
                $this->zafiksirovatBazoviyHead($unit->unitId, $sostoyanie->posledniyKommitUnixtime);

                return false;
            }

            if ($sostoyanie->posledniyKommitUnixtime <= $unit->unixtimePoslednegoObnovleniyaVHranilishe) {
                return false;
            }
        } else {
            /*
             * Юнит на основной ветке не удаляем ни по слиянию, ни по удалению:
             * такие события относятся к рабочим веткам.
             */
            if ($unit->branch === $unit->mainBranch) {
                return false;
            }

            if (!$unit->avtoudalenie) {
                return false;
            }
        }

        $dannieSobitiya = new DannieSobitiya(
            $tipSobitiya,
            $unit->branch,
            $sostoyanie->posledniyKommitUnixtime ?? time(),
            $sostoyanie->status === StatusVetki::MERGED ? $unit->mainBranch : null,
        );

        try {
            $result = $this->obrabotatSostoyanie->handle(
                $this->unitRepository->getById($unit->unitId),
                $tipSobitiya,
                $dannieSobitiya,
            );
        } catch (\Throwable $e) {
            $this->logger->error('unit.polling_vetok.unit_ne_obrabotan', [
                'unitId' => $unit->unitId,
                'tipSobitiya' => $tipSobitiya->value,
                'exception' => $e,
            ]);

            return false;
        }

        if (!in_array($result, [
            ObrabotatSostoyanieVetkiUseCase::OK,
            ObrabotatSostoyanieVetkiUseCase::DEFERRED,
            ObrabotatSostoyanieVetkiUseCase::HELD,
            ObrabotatSostoyanieVetkiUseCase::NOT_NEEDED,
            ObrabotatSostoyanieVetkiUseCase::PENDING_DELETE,
        ], true)) {
            $this->logger->warning('unit.polling_vetok.otkaz', [
                'unitId' => $unit->unitId,
                'tipSobitiya' => $tipSobitiya->value,
                'result' => $result,
            ]);
        }

        return true;
    }

    private function zafiksirovatBazoviyHead(string $unitId, int $unixtime): void
    {
        try {
            $unit = $this->unitRepository->getById($unitId);
            $unit->izmenitVremyaPoslednegoIzmeneniyaKodaVHranilishe($unixtime);
            $this->unitRepository->save($unit);
        } catch (\Throwable $e) {
            $this->logger->warning('unit.polling_vetok.bazoviy_head_ne_zafiksirovan', [
                'unitId' => $unitId,
                'exception' => $e,
            ]);
        }
    }
}
