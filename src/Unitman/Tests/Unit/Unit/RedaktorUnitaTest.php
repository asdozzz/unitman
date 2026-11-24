<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\Event\RedaktirovanieUnitaVklucheno;
use App\Unitman\Business\Model\Unit\Event\RedaktirovanieUnitaVyklucheno;
use App\Unitman\Business\Model\Unit\Event\RedaktorOtkreplenOtUnitu;
use App\Unitman\Business\Model\Unit\Event\RedaktorPrikreplenKUnitu;
use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Флаг редактирования и список редакторов юнита.
 */
final class RedaktorUnitaTest extends TestCase
{
    private const UNIT_ID = 'unit-1';
    private const PROJECT_ID = 'project-1';

    #[Test]
    public function novii_unit_bez_redaktirovaniya_i_redaktorov(): void
    {
        $unit = $this->sozdatUnit('feature/1');

        self::assertFalse($unit->getRedaktirovanieVklucheno());
        self::assertSame([], $unit->getRedaktory());
    }

    #[Test]
    public function vklyuchenie_i_vykluchenie_pishut_sobitiya(): void
    {
        $unit = $this->sozdatUnit('feature/1');
        $project = $this->sozdatProekt([]);
        $unit->releaseEvents();

        $unit->vklyuchitRedaktirovanie($project);
        self::assertTrue($unit->getRedaktirovanieVklucheno());

        $unit->vykluchitRedaktirovanie($project);
        self::assertFalse($unit->getRedaktirovanieVklucheno());

        $events = $unit->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(RedaktirovanieUnitaVklucheno::class, $events[0]);
        self::assertInstanceOf(RedaktirovanieUnitaVyklucheno::class, $events[1]);
    }

    #[Test]
    public function povtornoe_vklyuchenie_idempotentno(): void
    {
        $unit = $this->sozdatUnit('feature/1');
        $project = $this->sozdatProekt([]);
        $unit->vklyuchitRedaktirovanie($project);
        $unit->releaseEvents();

        $unit->vklyuchitRedaktirovanie($project);

        self::assertSame([], $unit->releaseEvents());
        self::assertTrue($unit->getRedaktirovanieVklucheno());
    }

    #[Test]
    public function zashishennaya_vetka_zapreshaet_vklyuchenie(): void
    {
        $unit = $this->sozdatUnit('release/1.2');
        $project = $this->sozdatProekt(['release/*']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.vetka_zashishena');

        $unit->vklyuchitRedaktirovanie($project);
    }

    #[Test]
    public function prikreplenie_i_otkreplenie_redaktora(): void
    {
        $unit = $this->sozdatUnit('feature/1');
        $project = $this->sozdatProekt([]);
        $unit->releaseEvents();

        $unit->prikrepitRedaktora('user-2', $project);
        self::assertSame(['user-2'], $unit->getRedaktory());

        $unit->otkrepitRedaktora('user-2', $project);
        self::assertSame([], $unit->getRedaktory());

        $events = $unit->releaseEvents();
        self::assertCount(2, $events);
        self::assertInstanceOf(RedaktorPrikreplenKUnitu::class, $events[0]);
        self::assertSame('user-2', $events[0]->userId);
        self::assertInstanceOf(RedaktorOtkreplenOtUnitu::class, $events[1]);
    }

    #[Test]
    public function povtornoe_prikreplenie_zapresheno(): void
    {
        $unit = $this->sozdatUnit('feature/1');
        $project = $this->sozdatProekt([]);
        $unit->prikrepitRedaktora('user-2', $project);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.redaktor.redaktor_uzhe_prikreplen');

        $unit->prikrepitRedaktora('user-2', $project);
    }

    #[Test]
    public function otkreplenie_neaaprikreplennogo_idempotentno(): void
    {
        $unit = $this->sozdatUnit('feature/1');
        $project = $this->sozdatProekt([]);
        $unit->releaseEvents();

        $unit->otkrepitRedaktora('user-2', $project);

        self::assertSame([], $unit->releaseEvents());
    }

    #[Test]
    public function udaleniy_unit_ne_menyaet_rezhim(): void
    {
        $unit = $this->sozdatUnit('feature/1');
        $project = $this->sozdatProekt([]);
        (new \ReflectionProperty(Unit::class, 'isDeleted'))->setValue($unit, true);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.udalen');

        $unit->vklyuchitRedaktirovanie($project);
    }

    private function sozdatUnit(string $branch): Unit
    {
        $unit = Unit::sozdatUnit(
            self::UNIT_ID,
            'author-1',
            'prozes-1',
            new SozdatUnit(self::PROJECT_ID, 'unit-name', $branch, [], 3000),
        );

        // Исходное событие создания не относится к проверяемым действиям.
        $events = $unit->releaseEvents();
        self::assertInstanceOf(UnitSozdan::class, $events[0]);

        return $unit;
    }

    /**
     * @param string[] $vetki
     */
    private function sozdatProekt(array $vetki): Project
    {
        return Project::addProject(
            self::PROJECT_ID,
            new AddProject('repo-1', 'group/project', 'Project', 'main',
                300,
                $vetki,
            ),
            'author-1',
        );
    }
}
