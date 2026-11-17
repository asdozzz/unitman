<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\TipZanyatosti;
use App\Unitman\Business\Model\Unit\ZanyatostUnita;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;
use App\Unitman\Business\UseCase\Unit\DobavitProzesUdaleniyaUseCase;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;
use App\Unitman\Tests\Support\FakeUnitmanSecurityService;
use App\Unitman\Tests\Support\SequenceGuidGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Обратный гейт: пока по юниту идёт ход OpenCode, ставить его в удаление нельзя.
 *
 * Проверка стоит до мутации агрегата, поэтому при занятости юнит не должен
 * даже попасть в репозиторий: иначе процессор заданий увидит новый процесс и
 * начнёт останавливать контейнер, который прямо сейчас правит OpenCode.
 */
final class DobavitProzesUdaleniyaUseCaseTest extends TestCase
{
    private const UNIT_ID = 'unit-1';

    #[Test]
    public function zanyatii_hodom_opencode_unit_ne_udalyaetsya(): void
    {
        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->expects($this->once())->method('getById')->willReturn($this->unit());
        $unitRepository->expects($this->never())->method('save');

        $useCase = $this->useCase($unitRepository, $this->zanyatostHodomOpenCode());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.zanyat_hodom_opencode');

        $useCase->handle(self::UNIT_ID);
    }

    #[Test]
    public function sistemnii_scenarii_takzhe_upiraetsya_v_gate(): void
    {
        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->expects($this->once())->method('getById')->willReturn($this->unit());
        $unitRepository->expects($this->never())->method('save');

        $useCase = $this->useCase($unitRepository, $this->zanyatostHodomOpenCode());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.zanyat_hodom_opencode');

        $useCase->handleSystem(self::UNIT_ID);
    }

    #[Test]
    public function svobodnii_unit_na_udalenie_stavitsya(): void
    {
        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->expects($this->once())->method('getById')->willReturn($this->unit());
        $unitRepository->expects($this->once())->method('save');

        $useCase = $this->useCase($unitRepository, null);

        $useCase->handle(self::UNIT_ID);
    }

    private function useCase(UnitRepository $unitRepository, ?ZanyatostUnita $zanyatost): DobavitProzesUdaleniyaUseCase
    {
        $zanyatostRepository = $this->createMock(ZanyatostUnitaRepository::class);
        $zanyatostRepository->method('poluchitAktivnuyu')->willReturn($zanyatost);

        return new DobavitProzesUdaleniyaUseCase(
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
        return Unit::sozdatUnit(
            self::UNIT_ID,
            'author-1',
            'prozes-1',
            new SozdatUnit('project-1', 'unit-name', 'feature/1', [], 3000),
        );
    }
}
