<?php

namespace App\Unitman\Tests\UseCase\Unit;

use App\Unitman\Business\Command\Unit\ObnovitStatistikuPoKontaineruUnita;
use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\ReadModel\Unit\ProjectListContainerStats;
use App\Unitman\Business\UseCase\Unit\DobavitProzesSborkiUseCase;
use App\Unitman\Business\UseCase\Unit\ObnovitStatistikuPoKonteineruUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\ObrabotatProzesiUnitaUseCase;
use App\Unitman\Business\UseCase\Unit\SozdatUnitUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Unit\SpisokUnitovRepository;
use Ramsey\Uuid\Uuid;

final class ObnovlenieStatistikiProektaTest extends AbstractUnitUseCase
{
    /**
     * @test
     * */
    function obnovlenieStatistikiUnita()
    {
        $unitId = Uuid::uuid7()->toString();
        $userId = Uuid::uuid7()->toString();
        $securityService = $this->mokaemUspehSecurity($userId);
        $projectId = Uuid::uuid7()->toString();

        $prozesId = Uuid::uuid7()->toString();
        $guidGeneratorArr = [$unitId, $prozesId];
        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator($guidGeneratorArr));

        $project = $this->stubProekta($projectId, $userId, 'uwin');
        $projectRepository = self::$container->get(ProjectRepository::class);
        /** @var $projectRepository ProjectRepository*/
        $projectRepository->save($project);



        $spisokUnitovRepo = self::$container->get(SpisokUnitovRepository::class);
        /** @var SpisokUnitovRepository $spisokUnitovRepo */


        $unitRepo = self::$container->get(UnitRepository::class);
        $useCase2 = new SozdatUnitUseCase($spisokUnitovRepo, $projectRepository, $securityService, new MemoryGuidGenerator($guidGeneratorArr), $unitRepo);
        $id = $useCase2->handle(new SozdatUnit(
            $projectId,
            'unit-1',
            'feature/123',
            [],
            500
        ));

        $this->assertEquals($id, $unitId);



        $sut = self::$container->get(ObnovitStatistikuPoKonteineruUnitaUseCase::class);
        /** @var $sut ObnovitStatistikuPoKonteineruUnitaUseCase*/
        $sut->handle(new ObnovitStatistikuPoKontaineruUnita('unit-1.uwin', '5%', '10%', '1gb', '10/20'));

        $readModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($readModel->statistikaKonteinera, new ProjectListContainerStats('5%', '10%', '1gb', '10/20'));

        $sut = self::$container->get(ObnovitStatistikuPoKonteineruUnitaUseCase::class);
        /** @var $sut ObnovitStatistikuPoKonteineruUnitaUseCase*/
        $sut->handle(new ObnovitStatistikuPoKontaineruUnita('unit-1.uwin', '10%', '20%', '2gb', '20/30'));

        $readModel = $spisokUnitovRepo->getById($unitId);
        $this->assertEquals($readModel->statistikaKonteinera, new ProjectListContainerStats('10%', '20%', '2gb', '20/30'));
    }
}
