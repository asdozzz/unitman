<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Unit\PoluchitSostoyanieUnita;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\UnitId;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\CanGetUnitReadModelById;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\Unit\ZanyatostUnitaRepository;
use App\Unitman\Business\ReadModel\Unit\SostoyanieUnitaReadModel;
use App\Unitman\Business\ReadModel\Unit\SpisokUnitovReadModel;
use App\Unitman\Business\UseCase\Unit\PoluchitSostoyanieUnitaQuery;
use App\Unitman\Business\UseCase\Unit\PoluchitZanyatostUnitaQuery;
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

        $query = $this->query($repository);

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

        $query = $this->query($repository);

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

        $query = $this->query($repository);

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

        $query = $this->query($repository);

        $this->assertOtsutstvuet($query->handle(new PoluchitSostoyanieUnita(self::UNIT_ID)));
    }

    #[Test]
    public function container_state_mismatch_probrasyvaetsya_v_read_model(): void
    {
        $unit = Unit::sozdatUnit(
            self::UNIT_ID,
            'author-1',
            'prozes-1',
            new SozdatUnit('project-1', 'unit-name', 'feature/1', [], 3000),
        );

        $repository = $this->createMock(UnitRepository::class);
        $repository->method('getById')->willReturn($unit);

        $unitReadModelRepository = $this->createMock(CanGetUnitReadModelById::class);
        $unitReadModelRepository->expects($this->once())
            ->method('getById')
            ->with(self::UNIT_ID)
            ->willReturn(new SpisokUnitovReadModel(
                self::UNIT_ID,
                'author-1',
                'Author',
                'unit-name',
                'project-1',
                'Project',
                'feature/1',
                containerStateMismatch: true,
            ));

        $query = $this->query($repository, null, $unitReadModelRepository);
        $readModel = $query->handle(new PoluchitSostoyanieUnita(self::UNIT_ID));

        self::assertTrue($readModel->containerStateMismatch);
    }

    #[Test]
    public function sostoyanie_soderzhit_redaktorov_i_zashishennost_vetki(): void
    {
        $project = Project::addProject(
            'project-1',
            new AddProject('repo-1', 'group/project', 'Project', 'main', 'https://proxy.local', 300, ['main']),
            'author-1',
        );

        $unit = Unit::sozdatUnit(
            self::UNIT_ID,
            'author-1',
            'prozes-1',
            new SozdatUnit('project-1', 'unit-name', 'feature/1', [], 3000),
        );
        $unit->vklyuchitRedaktirovanie($project);
        $unit->prikrepitRedaktora('editor-1', $project);

        $repository = $this->createMock(UnitRepository::class);
        $repository->method('getById')->willReturn($unit);

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('getById')->willReturn($project);

        $query = $this->query($repository, $projectRepository);
        $readModel = $query->handle(new PoluchitSostoyanieUnita(self::UNIT_ID));

        self::assertTrue($readModel->redaktirovanieVklucheno);
        self::assertFalse($readModel->vetkaZashishena);
        self::assertSame(['editor-1'], $readModel->redaktory);
        self::assertSame('author-1', $readModel->authorId);
    }

    #[Test]
    public function sostoyanie_otmechaet_zashishennuyu_vetku(): void
    {
        $project = Project::addProject(
            'project-1',
            new AddProject('repo-1', 'group/project', 'Project', 'main', 'https://proxy.local', 300, ['release/*']),
            'author-1',
        );

        $unit = Unit::sozdatUnit(
            self::UNIT_ID,
            'author-1',
            'prozes-1',
            new SozdatUnit('project-1', 'unit-name', 'release/1.2', [], 3000),
        );

        $repository = $this->createMock(UnitRepository::class);
        $repository->method('getById')->willReturn($unit);

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('getById')->willReturn($project);

        $query = $this->query($repository, $projectRepository);
        $readModel = $query->handle(new PoluchitSostoyanieUnita(self::UNIT_ID));

        self::assertTrue($readModel->vetkaZashishena);
        self::assertFalse($readModel->redaktirovanieVklucheno);
        self::assertSame([], $readModel->redaktory);
    }

    private function query(
        UnitRepository $repository,
        ?ProjectRepository $projectRepository = null,
        ?CanGetUnitReadModelById $unitReadModelRepository = null,
    ): PoluchitSostoyanieUnitaQuery
    {
        $zanyatostRepository = $this->createMock(ZanyatostUnitaRepository::class);
        $zanyatostRepository->method('poluchitAktivnuyu')->willReturn(null);

        if ($projectRepository === null) {
            $projectRepository = $this->createMock(ProjectRepository::class);
            $projectRepository
                ->method('getById')
                ->willThrowException(new \DomainException('project.not_found'));
        }

        if ($unitReadModelRepository === null) {
            $unitReadModelRepository = $this->createMock(CanGetUnitReadModelById::class);
            $unitReadModelRepository
                ->method('getById')
                ->willThrowException(new \Exception('unit.spisok_unitov.unit_not_found'));
        }

        return new PoluchitSostoyanieUnitaQuery(
            $repository,
            $projectRepository,
            new PoluchitZanyatostUnitaQuery($repository, $zanyatostRepository),
            $unitReadModelRepository,
        );
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