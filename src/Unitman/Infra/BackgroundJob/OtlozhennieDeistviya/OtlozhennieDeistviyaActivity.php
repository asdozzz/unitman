<?php

declare(strict_types=1);

namespace App\Unitman\Infra\BackgroundJob\OtlozhennieDeistviya;

use App\Unitman\Business\Port\Unit\OtlozhennieDeistviyaRepository;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;
use App\Unitman\Business\UseCase\Unit\PrimenitOtlozhennieDeistviyaDlyaUnitaUseCase;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Фоновая страховка применения отложенных вебхук-намерений.
 *
 * Немедленные пути (освобождение юнита и завершение последней задачи процесса)
 * покрывают обычный сценарий, но не все: процесс мог умереть между записью
 * намерения и его применением, а захват юнита мог истечь по TTL, пока ход
 * OpenCode висел. Тогда намерение остаётся в таблице без единого повода
 * к применению, и без этого обхода оно висело бы вечно.
 *
 * Пачка обходится в одной транзакции: `select ... for update skip locked`
 * удерживает блокировки строк только до конца транзакции, поэтому без явной
 * транзакции несколько процессов job'а выбирали бы одни и те же строки
 * (та же ошибка, что была в очереди юнитов). Применение каждого юнита идёт
 * во вложенной транзакции: падение на одном намерении откатывает только его,
 * остальные в пачке доходят до конца.
 */
final class OtlozhennieDeistviyaActivity
{
    const BATCH_LIMIT = 50;

    /**
     * Намерение старше суток уже не описывает актуальное состояние ветки:
     * применять его опаснее, чем выбросить.
     */
    const USTAREVSHIE_CHASOV = 24;

    public function __construct(
        private OtlozhennieDeistviyaRepository $otlozhennieRepository,
        private ZanyatostUnitaRepository $zanyatostRepository,
        private PrimenitOtlozhennieDeistviyaDlyaUnitaUseCase $primenitOtlozhennie,
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return int сколько юнитов с намерениями разобрано
     */
    public function obrabotatOchered(\DateTimeImmutable $now, int $limit = self::BATCH_LIMIT): int
    {
        /*
         * Страховка на случай, если автообновление включили в обход
         * ObnovitNastroikiHukaUseCase. Делаем до транзакции пачки, чтобы
         * освобождённые строки попали в текущую выборку.
         */
        $this->otlozhennieRepository->osvoboditUderzhannieDlyaVsehProektov($now);

        $this->connection->beginTransaction();

        try {
            $unitIds = $this->sobratIdUnitov($now, $limit);

            foreach ($unitIds as $unitId) {
                $this->obrabotatUnit($unitId);
            }

            $this->connection->commit();

            return count($unitIds);
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Убирает то, что уже не имеет смысла держать: истёкшие захваты и
     * просроченные намерения. Выполняется вне транзакции пачки, чтобы
     * не удлинять её.
     */
    public function ochistit(\DateTimeImmutable $now): void
    {
        $zanyatostey = $this->zanyatostRepository->udalitProsrochennie($now);
        $deistviy = $this->otlozhennieRepository->udalitUstarevshie(
            $now->modify(sprintf('-%d hours', self::USTAREVSHIE_CHASOV))
        );

        if ($zanyatostey > 0 || $deistviy > 0) {
            $this->logger->info('unit.otlozhennie_deistviya.ochistka', [
                'zanyatostey' => $zanyatostey,
                'deistviy' => $deistviy,
            ]);
        }
    }

    /**
     * @return string[]
     */
    private function sobratIdUnitov(\DateTimeImmutable $now, int $limit): array
    {
        $unitIds = [];

        foreach ($this->otlozhennieRepository->poluchitGotovie($now, $limit) as $deistvie) {
            $unitIds[$deistvie->unitId] = $deistvie->unitId;
        }

        return array_values($unitIds);
    }

    /**
     * Вложенная транзакция даёт savepoint: ошибка одного юнита откатывает
     * только его изменения, пачка продолжается.
     */
    private function obrabotatUnit(string $unitId): void
    {
        $this->connection->beginTransaction();

        try {
            $this->primenitOtlozhennie->handle($unitId);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            /*
             * Не пробрасываем: остальные юниты пачки должны быть обработаны.
             * Намерение осталось в таблице, поэтому следующий проход повторит
             * попытку — бюджет ретраев считает сам UseCase.
             */
            $this->logger->error('unit.otlozhennie_deistviya.unit_failed', [
                'unitId' => $unitId,
                'exception' => $e,
            ]);
        }
    }
}
