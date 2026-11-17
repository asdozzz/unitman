<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Model\Unit\TipZanyatosti;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;
use App\Unitman\Business\UseCase\Unit\ZahvatitZanyatostUnitovUseCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Захват юнитов под внешнюю операцию обязан быть «всё или ничего».
 *
 * Мультиюнитная сессия OpenCode работает сразу с несколькими юнитами. Если
 * удалось занять только часть, оставшиеся остались бы открытыми для действий
 * человека, пока OpenCode уже пишет в них файлы. Поэтому при первом отказе
 * захваченные ранее юниты должны быть освобождены, а вызывающая сторона —
 * получить `unit.zanyat`.
 */
final class ZahvatitZanyatostUnitovUseCaseTest extends TestCase
{
    private const VLADELEC = 'opencode:turn-1';

    #[Test]
    public function pustoie_znachenie_ne_hodit_v_repozitorii(): void
    {
        $repository = $this->createMock(ZanyatostUnitaRepository::class);
        $repository->expects($this->never())->method('zahvatit');
        $repository->expects($this->never())->method('osvobodit');

        $useCase = new ZahvatitZanyatostUnitovUseCase($repository);

        $useCase->handle([], TipZanyatosti::OPENCODE_HOD, self::VLADELEC);
        $useCase->handle(['', '   '], TipZanyatosti::OPENCODE_HOD, self::VLADELEC);
    }

    #[Test]
    public function identifikatori_obrezayutsya_sortiruyutsya_i_ubiraiutsya_dubli(): void
    {
        $poriadok = [];

        $repository = $this->createMock(ZanyatostUnitaRepository::class);
        $repository->expects($this->exactly(2))
            ->method('zahvatit')
            ->willReturnCallback(
                static function (string $unitId) use (&$poriadok): bool {
                    $poriadok[] = $unitId;

                    return true;
                }
            );
        $repository->expects($this->never())->method('osvobodit');

        $useCase = new ZahvatitZanyatostUnitovUseCase($repository);

        $useCase->handle([' unit-2', 'unit-1 ', 'unit-2', ''], TipZanyatosti::OPENCODE_HOD, self::VLADELEC);

        self::assertSame(['unit-1', 'unit-2'], $poriadok, 'Сортировка защищает от взаимной блокировки двух ходов');
    }

    #[Test]
    public function vremya_ischecheniya_zahvata_beretsya_iz_ttl(): void
    {
        $do = null;

        $repository = $this->createMock(ZanyatostUnitaRepository::class);
        $repository->expects($this->once())
            ->method('zahvatit')
            ->willReturnCallback(
                static function (
                    string $unitId,
                    string $vladelec,
                    TipZanyatosti $tip,
                    \DateTimeImmutable $istekaetV,
                ) use (&$do): bool {
                    $do = $istekaetV;

                    return true;
                }
            );

        $useCase = new ZahvatitZanyatostUnitovUseCase($repository);
        $useCase->handle(['unit-1'], TipZanyatosti::OPENCODE_HOD, self::VLADELEC);

        self::assertInstanceOf(\DateTimeImmutable::class, $do);
        $ozhidaemo = (new \DateTimeImmutable())->modify('+' . ZahvatitZanyatostUnitovUseCase::TTL_MINUTES . ' minutes');
        self::assertEqualsWithDelta(
            $ozhidaemo->getTimestamp(),
            $do->getTimestamp(),
            3,
            'TTL захвата должен перекрывать WorkflowExecutionTimeout хода OpenCode'
        );
    }

    #[Test]
    public function otkaz_na_vtorom_unite_osvobozhdaet_pervii(): void
    {
        $osvobozhdeno = [];

        $repository = $this->createMock(ZanyatostUnitaRepository::class);
        $repository->expects($this->exactly(2))
            ->method('zahvatit')
            ->willReturnCallback(
                static function (string $unitId): bool {
                    return $unitId === 'unit-1';
                }
            );
        $repository->expects($this->once())
            ->method('osvobodit')
            ->willReturnCallback(
                static function (string $unitId, string $vladelec) use (&$osvobozhdeno): void {
                    $osvobozhdeno[] = [$unitId, $vladelec];
                }
            );

        $useCase = new ZahvatitZanyatostUnitovUseCase($repository);

        try {
            $useCase->handle(['unit-1', 'unit-2'], TipZanyatosti::OPENCODE_HOD, self::VLADELEC);
            self::fail('Ожидалось исключение unit.zanyat');
        } catch (\DomainException $e) {
            self::assertSame('unit.zanyat', $e->getMessage());
        }

        self::assertSame([['unit-1', self::VLADELEC]], $osvobozhdeno);
    }

    #[Test]
    public function otkaz_na_pervom_unite_nichego_ne_osvobozhdaet(): void
    {
        $repository = $this->createMock(ZanyatostUnitaRepository::class);
        $repository->expects($this->once())->method('zahvatit')->willReturn(false);
        $repository->expects($this->never())->method('osvobodit');

        $useCase = new ZahvatitZanyatostUnitovUseCase($repository);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.zanyat');

        $useCase->handle(['unit-1'], TipZanyatosti::OPENCODE_HOD, self::VLADELEC);
    }

    #[Test]
    public function uspeshnii_zahvat_ne_osvobozhdaet_nichego(): void
    {
        $repository = $this->createMock(ZanyatostUnitaRepository::class);
        $repository->expects($this->exactly(2))->method('zahvatit')->willReturn(true);
        $repository->expects($this->never())->method('osvobodit');

        $useCase = new ZahvatitZanyatostUnitovUseCase($repository);

        $useCase->handle(['unit-1', 'unit-2'], TipZanyatosti::OPENCODE_HOD, self::VLADELEC);
    }
}
