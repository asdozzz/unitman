<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\PoluchitSostoyanieUnita;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\UnitId;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\Unit\SostoyanieUnitaReadModel;
use App\Unitman\Business\UseCase\Unit\PoluchitSostoyanieUnitaQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Чтение состояния юнита для внешних модулей (сессии OpenCode).
 *
 * Запрос обязан быть отказоустойчивым: интеграция опрашивает состояние
 * постоянно, поэтому "юнита нет" — это валидный ответ, а не ошибка.
 * Пустой идентификатор вообще не доходит до репозитория.
 */
final class PoluchitSostoyanieUnitaQueryTest extends TestCase
{
    private const UNIT_ID = 'unit-1';

    #[Test]
    public function pustoie_id_ne_hodit_v_repozitorii(): void
    {
        $repository = $this->createMock(UnitRepository::class);
        $repository->expects($this->never())->method('getById');

        $query = new PoluchitSostoyanieUnitaQuery($repository);

        $this->assertOtsutstvuet($query->handle(new PoluchitSostoyanieUnita('   ')));
    }

    #[Test]
    public function otsutstvuyuschi_unit_ne_brosaet_ischercheniya(): void
    {
        $repository = $this->createMock(UnitRepository::class);
        $repository->expects($this->once())
            ->method('getById')
            ->with(self::UNIT_ID)
            ->willThrowException(new \DomainException('unit.not_found'));

        $query = new PoluchitSostoyanieUnitaQuery($repository);

        $this->assertOtsutstvuet($query->handle(new PoluchitSostoyanieUnita(self::UNIT_ID)));
    }

    #[Test]
    public function sostoyanie_sozdannogo_unita_otdaetsya_celikom(): void
    {
        $unit = Unit::sozdatUnit(
            self::UNIT_ID,
            'author-1',
            'prozes-1',
            new SozdatUnit('project-1', 'unit-name', 'feature/1', [], 3000),
        );

        $repository = $this->createMock(UnitRepository::class);
        $repository->expects($this->once())
            ->method('getById')
            ->with(self::UNIT_ID)
            ->willReturn($unit);

        $query = new PoluchitSostoyanieUnitaQuery($repository);

        $readModel = $query->handle(new PoluchitSostoyanieUnita(self::UNIT_ID));

        $this->assertTrue($readModel->exists);
        $this->assertSame('SOZDAN', $readModel->code);
        $this->assertSame('project-1', $readModel->projectId);
        $this->assertSame('unit-name', $readModel->name);
        $this->assertSame('feature/1', $readModel->branch);
        $this->assertSame('author-1', $readModel->authorId);
    }

    #[Test]
    public function unit_bez_sostoyaniya_schitaetsya_otsutstvuyuschim(): void
    {
        $unit = $this->unitBezSostoyaniya();

        $repository = $this->createMock(UnitRepository::class);
        $repository->expects($this->once())
            ->method('getById')
            ->willReturn($unit);

        $query = new PoluchitSostoyanieUnitaQuery($repository);

        $this->assertOtsutstvuet($query->handle(new PoluchitSostoyanieUnita(self::UNIT_ID)));
    }

    private function assertOtsutstvuet(SostoyanieUnitaReadModel $readModel): void
    {
        $this->assertFalse($readModel->exists);
        $this->assertNull($readModel->code);
        $this->assertNull($readModel->projectId);
        $this->assertNull($readModel->name);
        $this->assertNull($readModel->branch);
        $this->assertNull($readModel->authorId);
    }

    /**
     * Реставрация без событий оставляет состояние неинициализированным:
     * агрегат должен уметь отвечать "состояния нет" и не ронять опрос.
     */
    private function unitBezSostoyaniya(): Unit
    {
        $unit = Unit::reconstituteFromEvents(UnitId::fromString(self::UNIT_ID), $this->pustoiPotok());

        (new \ReflectionProperty(Unit::class, 'state'))->setValue($unit, null);

        return $unit;
    }

    private function pustoiPotok(): \Generator
    {
        foreach ([] as $event) {
            yield $event;
        }

        return 0;
    }
}