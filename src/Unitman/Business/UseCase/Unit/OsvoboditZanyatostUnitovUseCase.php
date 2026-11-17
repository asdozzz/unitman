<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;

/**
 * Освобождение юнитов после завершения внешней операции.
 *
 * Освобождение идемпотентно: если ход упал и TTL уже истёк, строки в реестре
 * может не быть, и это не ошибка. Сразу после освобождения делается попытка
 * применить отложенные действия из вебхука — юнит только что стал доступен,
 * и ждать следующего прохода фонового job'а незачем.
 */
final class OsvoboditZanyatostUnitovUseCase
{
    public function __construct(
        private ZanyatostUnitaRepository $zanyatostRepository,
        private PrimenitOtlozhennieDeistviyaDlyaUnitaUseCase $primenitOtlozhennie,
    ) {
    }

    /**
     * @param string[] $unitIds
     */
    public function handle(array $unitIds, string $vladelec): void
    {
        foreach ($this->normalize($unitIds) as $unitId) {
            $this->zanyatostRepository->osvobodit($unitId, $vladelec);
        }

        foreach ($this->normalize($unitIds) as $unitId) {
            $this->primenitOtlozhennie->handle($unitId);
        }
    }

    /**
     * @param string[] $unitIds
     * @return string[]
     */
    private function normalize(array $unitIds): array
    {
        $result = [];
        foreach ($unitIds as $unitId) {
            $unitId = trim($unitId);
            if ($unitId !== '') {
                $result[$unitId] = $unitId;
            }
        }

        return array_values($result);
    }
}
