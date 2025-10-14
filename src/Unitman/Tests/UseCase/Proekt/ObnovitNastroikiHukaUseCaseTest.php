<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\ObnovitNastroikiHuka;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\ProjectList\NastroikiHukaProekta;
use App\Unitman\Business\UseCase\Project\ObnovitNastroikiHukaUseCase;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use Ramsey\Uuid\Uuid;

final class ObnovitNastroikiHukaUseCaseTest extends AbstractProjectUseCase
{
    /**
     * @test
     * */
    function nastroiki_huka_obnovleni()
    {
        $repoId = Uuid::uuid7()->toString();
        $adminId = Uuid::uuid7()->toString();

        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturn($adminId);
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main', 'http://testcase.su', $adminId);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $project = $projectListRepo->getById($projectId);

        $this->assertEquals($project->nastroikiHukaProekta, new NastroikiHukaProekta(false, true, true, false));

        $useCase = self::$container->get(ObnovitNastroikiHukaUseCase::class);
        /** @var $useCase ObnovitNastroikiHukaUseCase*/
        $useCase->handle(new ObnovitNastroikiHuka($projectId, true, false, false, true));

        $project = $projectListRepo->getById($projectId);

        $this->assertEquals(new NastroikiHukaProekta(true, false, false, true), $project->nastroikiHukaProekta);
    }
}
