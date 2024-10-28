<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Model\Account;
use App\Unitman\Business\Model\Runner\JobId;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UmeetSobiratUnit;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\UseCase\Unit\SozdatUnitSystemoiUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use Ramsey\Uuid\Uuid;

final class AvtosborkaUnitaTest extends AbstractUnitUseCase
{
    /**
     * @test
     * */
    function unit_sobran_systemoi()
    {
        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */
        $spisokUnitovRepo->truncate();

        $unitId = Uuid::uuid7()->toString();
        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([$unitId]));

        $userId = Uuid::uuid7()->toString();
        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('getUserById')->willReturn(new Account($userId, 'asd@asd.ru'));
        $securityService->expects($this->any())->method('getSystemUser')->willReturn(new Account($userId, 'asd@asd.ru', true));
        self::$container->set(UnitmanSecurityService::class, $securityService);
        $projectId = Uuid::uuid7()->toString();
        $project = $this->stubProekta($projectId, $userId);
        $projectRepository = self::$container->get(ProjectRepository::class);
        /** @var $projectRepository ProjectRepository*/
        $projectRepository->save($project);

        $umeetSobiratUnit = $this->getMockBuilder(UmeetSobiratUnit::class)->getMock();
        $jobId = '123';
        $umeetSobiratUnit->expects($this->any())->method('sobratUnitOtLizaSystemi')->willReturn(new JobId($jobId));
        self::$container->set(UmeetSobiratUnit::class, $umeetSobiratUnit);

        $useCase = self::$container->get(SozdatUnitSystemoiUseCase::class);
        /** @var $useCase SozdatUnitSystemoiUseCase*/
        $useCase->handle($projectId, 'feature/123');

        /** @var SpisokUnitovRepository $spisokUnitovRepo */
        $spisokUnitovReadModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($spisokUnitovReadModel->unitSozdanSystemoi, true);

    }
}
