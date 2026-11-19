<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Command\Unit\VipolnitDeistviye;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\TipZanyatosti;
use App\Unitman\Business\Model\Unit\ZanyatostUnita;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;
use App\Unitman\Business\UseCase\Unit\DobavitProzesVipolneniyaDeistviyaUseCase;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;
use App\Unitman\Tests\Support\FakeUnitmanSecurityService;
use App\Unitman\Tests\Support\SequenceGuidGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Обратный гейт: действия над юнитом не выполняются во время хода OpenCode.
 *
 * Действие меняет файлы внутри контейнера, поэтому пересечение с правками
 * OpenCode недопустимо: гейт стоит до создания процесса действия.
 */
final class DobavitProzesVipolneniyaDeistviyaUseCaseTest extends TestCase
{
    private const UNIT_ID = 'unit-1';
    private const ACTION_ID = 'action-1';

    #[Test]
    public function zanyatii_hodom_opencode_unit_ne_vypolnyaet_deistvie(): void
    {
        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->expects($this->once())->method('getById')->willReturn($this->unit());
        $unitRepository->expects($this->never())->method('save');

        $useCase = $this->useCase($unitRepository, $this->zanyatostHodomOpenCode());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.zanyat_hodom_opencode');

        $useCase->handle($this->command());
    }

    #[Test]
    public function svobodnii_unit_prinimaet_deistvie(): void
    {
        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->expects($this->once())->method('getById')->willReturn($this->unit());
        $unitRepository->expects($this->once())->method('save');

        $useCase = $this->useCase($unitRepository, null);

        $useCase->handle($this->command());
    }

    private function command(): VipolnitDeistviye
    {
        return new VipolnitDeistviye(self::UNIT_ID, self::ACTION_ID, []);
    }

    private function useCase(UnitRepository $unitRepository, ?ZanyatostUnita $zanyatost): DobavitProzesVipolneniyaDeistviyaUseCase
    {
        $zanyatostRepository = $this->createMock(ZanyatostUnitaRepository::class);
        $zanyatostRepository->method('poluchitAktivnuyu')->willReturn($zanyatost);

        return new DobavitProzesVipolneniyaDeistviyaUseCase(
            $unitRepository,
            new FakeUnitmanSecurityService(),
            new SequenceGuidGenerator('prozes-'),
            new ProverkaSvobodyUnita($zanyatostRepository),
        );
    }

    private function zanyatostHodomOpenCode(): ZanyatostUnita
    {
        $now = new \DateTimeImmutable();

        return new ZanyatostUnita(
            self::UNIT_ID,
            'opencode:turn-1',
            TipZanyatosti::OPENCODE_HOD,
            $now,
            $now->modify('+20 minutes'),
        );
    }

    private function unit(): Unit
    {
        $unit = Unit::sozdatUnit(
            self::UNIT_ID,
            'author-1',
            'prozes-1',
            new SozdatUnit('project-1', 'unit-name', 'feature/1', [], 3000),
        );

        // sozdatUnit всегда добавляет незавершённый процесс сборки. Для
        // сценария «свободный юнит» очищаем процессы: иначе гейт
        // unit.wait_another_proccess срабатывает раньше проверки занятости.
        (new \ReflectionProperty(Unit::class, 'prozesi'))->setValue($unit, []);

        return $unit;
    }
}
