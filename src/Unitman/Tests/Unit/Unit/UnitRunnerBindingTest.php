<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Unit;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Model\RunnerState\MemoryInfo;
use App\Runner\Business\UseCase\RunnerSelectionService;
use App\Runner\Infra\Adapter\DefaultRunnerSelector;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use App\Runner\Tests\Support\InMemoryUnitRunnerRepository;
use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\MediatekaService;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\Unit\CanFindUnitDouble;
use App\Unitman\Business\Port\Unit\CanGetUnitReadModelById;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\Port\VolumeType\VolumeTypeFileRepository;
use App\Unitman\Business\UseCase\Unit\ProverkaFaylovVolumov;
use App\Unitman\Business\UseCase\Unit\ProverkaSvyazeyPeremenih;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Tests\Support\InMemoryUnitRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Привязка нового юнита к раннеру (`unit_runner`) при создании.
 *
 * Контракт: plans/runner-multi-runner.md §6-§7.
 */
final class UnitRunnerBindingTest extends TestCase
{
    #[Test]
    public function sozdanie_vybiraet_zhivoy_runner_i_privyazivaet_junit(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $bindings = new InMemoryUnitRunnerRepository();
        $units = new InMemoryUnitRepository();

        $this->useCase($runners, $bindings, $units)
            ->sozdatDlyaPolzovatelya($this->command(), 'author-1', 'unit-1', 'prozes-1');

        self::assertSame('runner-1', $bindings->findRunnerIdByUnitId('unit-1'));
        self::assertArrayHasKey('unit-1', $units->units);
    }

    #[Test]
    public function yavno_peredanniy_runner_imeet_prioritet(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $runners->save($this->liveRunner('runner-2'));
        $bindings = new InMemoryUnitRunnerRepository();

        $this->useCase($runners, $bindings)
            ->sozdatDlyaPolzovatelya($this->command(), 'author-1', 'unit-1', 'prozes-1', true, 'runner-2');

        self::assertSame('runner-2', $bindings->findRunnerIdByUnitId('unit-1'));
    }

    #[Test]
    public function neaktivniy_yavniy_runner_zapreshchaet_sozdanie_bez_rebind(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save($this->liveRunner('runner-1'));
        $runners->save(new RunnerState('runner-dead', 'runner-dead', false));
        $bindings = new InMemoryUnitRunnerRepository();
        $units = new InMemoryUnitRepository();

        $useCase = $this->useCase($runners, $bindings, $units);

        try {
            $useCase->sozdatDlyaPolzovatelya(
                $this->command(),
                'author-1',
                'unit-1',
                'prozes-1',
                true,
                'runner-dead',
            );
            self::fail('Ожидалась ошибка runner.inactive');
        } catch (\DomainException $error) {
            self::assertSame('runner.inactive', $error->getMessage());
        }

        self::assertNull($bindings->findRunnerIdByUnitId('unit-1'));
        self::assertSame([], $units->units);
    }

    #[Test]
    public function bez_zhivyh_runnerov_sozdanie_zapreshcheno_runner_not_found(): void
    {
        $runners = new InMemoryRunnerRepository();
        $bindings = new InMemoryUnitRunnerRepository();
        $units = new InMemoryUnitRepository();

        $useCase = $this->useCase($runners, $bindings, $units);

        try {
            $useCase->sozdatDlyaPolzovatelya($this->command(), 'author-1', 'unit-1', 'prozes-1');
            self::fail('Ожидалась ошибка runner.not_found');
        } catch (\DomainException $error) {
            self::assertSame('runner.not_found', $error->getMessage());
        }

        self::assertArrayNotHasKey('unit-1', $units->units);
        self::assertNull($bindings->findRunnerIdByUnitId('unit-1'));
    }

    private function useCase(
        InMemoryRunnerRepository $runners,
        InMemoryUnitRunnerRepository $bindings,
        ?InMemoryUnitRepository $units = null,
    ): SozdatUnitUseCase {
        $project = Project::addProject(
            'project-1',
            new AddProject('repo-1', 'project-code', 'my-app', 'master', memoryLimit: 500),
            'author-1',
        );
        // Проект активен сразу после создания; build-стадии нет.

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('getById')->willReturn($project);

        $double = $this->createMock(CanFindUnitDouble::class);
        $double->method('isExistsDoubleByName')->willReturn(false);

        $security = $this->createMock(UnitmanSecurityService::class);

        $guid = $this->createMock(CanGenerateGuid::class);
        $guid->method('makeGuid')->willReturn('generated');

        $mediateka = $this->createMock(MediatekaService::class);
        $volumeTypes = $this->createMock(VolumeTypeFileRepository::class);

        $projectIds = $this->createMock(UmeetPoluchatSpisokProektovDlyPolzovatelya::class);
        $projectIds->method('poluchitSpisokProektovDlyPolzovatelya')->willReturn([]);

        return new SozdatUnitUseCase(
            $double,
            $projectRepository,
            $security,
            $guid,
            $units ?? new InMemoryUnitRepository(),
            $mediateka,
            new ProverkaFaylovVolumov($mediateka, $volumeTypes),
            new ProverkaSvyazeyPeremenih($this->createMock(CanGetUnitReadModelById::class)),
            $projectIds,
            new RunnerSelectionService($runners, new DefaultRunnerSelector(), $bindings),
            $bindings,
        );
    }

    private function command(): SozdatUnit
    {
        return new SozdatUnit('project-1', 'task-123', 'feature/123', [], 500);
    }

    private function liveRunner(string $id): RunnerState
    {
        $runner = new RunnerState(
            $id,
            $id,
            true,
            publicAddress: 'https://' . $id . '.example.com',
        );
        $runner->ustanovitResultatRabotosposobnosti(
            true,
            [],
            new MemoryInfo(1000, 900),
            time(),
        );

        return $runner;
    }
}
