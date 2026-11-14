<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\ObnovitNastroikiHuka;
use App\Unitman\Business\Model\Account;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\ProjectList\NastroikiHukaProekta;
use App\Unitman\Business\UseCase\Project\ObnovitNastroikiHukaUseCase;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

final class ObnovitNastroikiHukaUseCaseTest extends AbstractProjectUseCase
{
    #[Test]
    function nastroiki_huka_obnovleni()
    {
        $repoId = Uuid::uuid7()->toString();
        $adminId = Uuid::uuid7()->toString();

        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturn($adminId);
        // Без этого стаба мок возвращает Account с неинициализированными
        // readonly-свойствами и проекция websocket-событий падает.
        $securityService->expects($this->any())->method('getSystemUser')->willReturn(new Account($adminId, 'asd@asd.ru', false));
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $projectId = $this->addProject($repoId, $this->poluchitUnikalnyiKodProekta(), $this->poluchitUnikalnoeImyaProekta(), 'main', 'http://testcase.su', $adminId);

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
