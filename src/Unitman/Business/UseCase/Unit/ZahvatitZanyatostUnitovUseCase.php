<?php

declare(strict_types=1);

namespace App\Unitman\Business\UseCase\Unit;

use App\Unitman\Business\Model\Unit\TipZanyatosti;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;

/**
 * Захват юнитов под внешнюю операцию (сейчас — ход OpenCode).
 *
 * Контракт «всё или ничего»: мультиюнитная сессия работает с несколькими
 * юнитами одновременно, и если удалось занять только часть, оставшиеся
 * останутся открытыми для действий человека, пока OpenCode уже пишет в них
 * файлы. Поэтому при первом отказе уже захваченные юниты освобождаются.
 *
 * Идентификаторы сортируются перед захватом: два параллельных хода берут
 * пересекающиеся наборы юнитов, и без общего порядка они могли бы встать
 * в клинч «каждый держит первого и ждёт второго». Сортировка превращает
 * возможный клинч в честный отказ одного из ходов.
 */
final class ZahvatitZanyatostUnitovUseCase
{
    /**
     * Сколько живёт захват. Строго больше `WorkflowExecutionTimeout`
     * у `OpenCodeTurnWorkflow` (15 минут), иначе действующий ход мог бы
     * потерять захват до собственного завершения.
     */
    const TTL_MINUTES = 20;

    public function __construct(private ZanyatostUnitaRepository $zanyatostRepository)
    {
    }

    /**
     * @param string[] $unitIds
     * @throws \DomainException если хотя бы один юнит занят
     */
    public function handle(array $unitIds, TipZanyatosti $tip, string $vladelec): void
    {
        $unitIds = $this->normalize($unitIds);

        if ($unitIds === []) {
            return;
        }

        $istekaetV = (new \DateTimeImmutable())->modify('+' . self::TTL_MINUTES . ' minutes');
        $zahvachennie = [];

        foreach ($unitIds as $unitId) {
            if (!$this->zanyatostRepository->zahvatit($unitId, $vladelec, $tip, $istekaetV)) {
                $this->otkatit($zahvachennie, $vladelec);

                throw new \DomainException('unit.zanyat');
            }

            $zahvachennie[] = $unitId;
        }
    }

    /**
     * @param string[] $zahvachennie
     */
    private function otkatit(array $zahvachennie, string $vladelec): void
    {
        foreach ($zahvachennie as $unitId) {
            $this->zanyatostRepository->osvobodit($unitId, $vladelec);
        }
    }

    /**
     * Убирает пустые значения и дубликаты, сортирует — см. комментарий класса.
     *
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

        $result = array_values($result);
        sort($result);

        return $result;
    }
}
