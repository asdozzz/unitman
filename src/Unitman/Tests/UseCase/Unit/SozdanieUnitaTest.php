<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit\VariableValue;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use Ramsey\Uuid\Uuid;

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
        /** @var $unitRepo UnitRepository*/

        $useCase2 = new SozdatUnitUseCase($spisokUnitovRepo, $projectRepository, $securityService, new MemoryGuidGenerator([$unitId,$unitId2,$unitId3]), $unitRepo);
        $useCase2->handle(new SozdatUnit(
            $projectId,
            'unit-1',
            'feature/123',
            [
                new SozdatUnit\ZnacheniePeremenoi('VARIABLE_1', 'VALUE_1', 'string'),
                new SozdatUnit\ZnacheniePeremenoi('VARIABLE_2', 'VALUE_2', 'integer')
            ]
        ));

        $unit = $unitRepo->getById($unitId);
        $this->assertEquals([
            new VariableValue('VARIABLE_1', 'VALUE_1', 'string'),
            new VariableValue('VARIABLE_2', 'VALUE_2', 'integer'),
        ],$unit->poluchitZnacheniyaPeremenih());

        $this->expectExceptionMessage('unit.name_already_exists');
        $useCase2->handle(new SozdatUnit(
            $projectId,
            'unit-1',
            'feature/222'
        ));

        $this->expectExceptionMessage('unit.name_invalid');
        $useCase2->handle(new SozdatUnit(
            $projectId,
            'unit 1',
            'feature/123'
        ));

        $this->expectExceptionMessage('unit.name_invalid');
        $useCase2->handle(new SozdatUnit(
            $projectId,
            'мультирасчет 23',
            'feature/123'
        ));
    }
}
