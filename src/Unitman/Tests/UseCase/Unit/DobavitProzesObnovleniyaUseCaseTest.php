<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\TipZanyatosti;
use App\Unitman\Business\Model\Unit\ZanyatostUnita;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\OtlozhennieDeistviyaRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;
use App\Unitman\Business\UseCase\Unit\DobavitProzesObnovleniyaUseCase;
use App\Unitman\Business\Utils\ProverkaSvobodyUnita;
use App\Unitman\Tests\Support\FakeUnitmanSecurityService;
use App\Unitman\Tests\Support\SequenceGuidGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Обратный гейт: занятый ходом OpenCode юнит не обновляется.
 *
 * Проверка обязана стоять до обращения к проекту: иначе гейт зависел бы от
 * существования проекта и падал бы другим исключением, маскируя занятость.
 */
final class DobavitProzesObnovleniyaUseCaseTest extends TestCase
{
    private const UNIT_ID = 'unit-1';

    #[Test]
    public function zanyatii_hodom_opencode_unit_ne_obnovlyaetsya(): void
    {
        $unitRepository = $this->createMock(UnitRepository::class);
        $unitRepository->expects($this->once())->method('getById')->willReturn($this->unit());
        $unitRepository->expects($this->never())->method('save');

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->expects($this->never())->method('getById');

        $useCase = $this->useCase($unitRepository, $projectRepository, $this->zanyatostHodomOpenCode());

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

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->expects($this->never())->method('getById');

        $useCase = $this->useCase($unitRepository, $projectRepository, $this->zanyatostHodomOpenCode());

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.zanyat_hodom_opencode');

        $useCase->handleSystem(self::UNIT_ID);
    }

    private function useCase(
        UnitRepository $unitRepository,
        ProjectRepository $projectRepository,
        ?ZanyatostUnita $zanyatost,
    ): DobavitProzesObnovleniyaUseCase {
        $zanyatostRepository = $this->createMock(ZanyatostUnitaRepository::class);
        $zanyatostRepository->method('poluchitAktivnuyu')->willReturn($zanyatost);

        return new DobavitProzesObnovleniyaUseCase(
            $unitRepository,
            new FakeUnitmanSecurityService(),
            new SequenceGuidGenerator('prozes-'),
            $projectRepository,
            new ProverkaSvobodyUnita($zanyatostRepository),
            $this->createMock(OtlozhennieDeistviyaRepository::class),
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
