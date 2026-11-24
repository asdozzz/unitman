<?php

declare(strict_types=1);

namespace App\Unitman\Tests\UseCase\Unit;

use App\Runner\Tests\Support\InMemoryUnitRunnerRepository;
use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Unit\Event\UnitSozdan;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Infra\Projection\SpisokUnitovProjection;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use EventSauce\EventSourcing\Message;
use PHPUnit\Framework\Attributes\Test;

/**
 * Проекция списка юнитов читает жёсткую привязку `unit_runner` и отдаёт её
 * в read-модели как `runnerId`.
 */
final class UnitRunnerProjectionBindingTest extends AbstractUnitUseCase
{
    private const UNIT_ID = 'unit-1';
    private const PROJECT_ID = 'project-1';

    private SpisokUnitovRepository $repository;

    public function setUp(): void
    {
        parent::setUp();

        $this->repository = self::$container->get(SpisokUnitovRepository::class);
        $this->repository->init();
        $this->repository->truncate();
    }

    #[Test]
    public function runnerId_beretsya_iz_privyazki(): void
    {
        $this->projection(new InMemoryUnitRunnerRepository([self::UNIT_ID => 'runner-7']))
            ->handle(new Message($this->sobitie()));

        self::assertSame('runner-7', $this->repository->getById(self::UNIT_ID)->runnerId);
    }

    #[Test]
    public function yunit_bez_privyazki_dayot_runnerId_null(): void
    {
        $this->projection(new InMemoryUnitRunnerRepository())
            ->handle(new Message($this->sobitie()));

        self::assertNull($this->repository->getById(self::UNIT_ID)->runnerId);
    }

    private function projection(InMemoryUnitRunnerRepository $bindings): SpisokUnitovProjection
    {
        $project = Project::addProject(
            self::PROJECT_ID,
            new AddProject('repo-1', 'my-app-code', 'my-app', 'master', memoryLimit: 500),
            'author-1',
        );

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('getById')->willReturn($project);

        return new SpisokUnitovProjection(
            $this->repository,
            $projectRepository,
            $this->createMock(UnitmanSecurityService::class),
            $bindings,
        );
    }

    private function sobitie(): UnitSozdan
    {
        return new UnitSozdan(
            self::UNIT_ID,
            'author-1',
            self::PROJECT_ID,
            'feature-123',
            'feature/123',
            ['commands' => []],
        );
    }
}
