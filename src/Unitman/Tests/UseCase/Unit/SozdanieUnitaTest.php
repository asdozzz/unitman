<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Clock\ClockInterface;

final class SozdanieUnitaTest extends AbstractUnitUseCase
{
    function test()
    {
        $unitId = Uuid::uuid7()->toString();
        $unitId2 = Uuid::uuid7()->toString();
        $unitId3 = Uuid::uuid7()->toString();
        $userId = Uuid::uuid7()->toString();
        $securityService = $this->mokaemUspehSecurity($userId);
        $projectId = Uuid::uuid7()->toString();
        $project = $this->stubProekta($projectId, $userId);
        $projectRepository = self::$container->get(ProjectRepository::class);
        /** @var $projectRepository ProjectRepository*/
        $projectRepository->save($project);

        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */

        $unitRepo = self::$container->get(UnitRepository::class);
        $useCase2 = new SozdatUnitUseCase($spisokUnitovRepo, $projectRepository, $securityService, new MemoryGuidGenerator([$unitId,$unitId2,$unitId3]), $unitRepo);
        $useCase2->handle(new SozdatUnit(
            $projectId,
            'unit-1',
            'feature/123'
        ));

        try {
            $useCase2->handle(new SozdatUnit(
                $projectId,
                'unit-1',
                'feature/222'
            ));
        } catch (\Exception $e) {
            $this->assertEquals('unit.name_already_exists', $e->getMessage());
        }

        try {
            $useCase2->handle(new SozdatUnit(
                $projectId,
                'unit 1',
                'feature/123'
            ));
        } catch (\Exception $e) {
            $this->assertEquals('unit.name_invalid', $e->getMessage());
        }
    }
}
