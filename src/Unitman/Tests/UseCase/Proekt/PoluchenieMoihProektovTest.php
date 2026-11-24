<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\AddUserToProject;
use App\Unitman\Business\Command\Project\PoluchitMoiProekti;
use App\Unitman\Business\Command\Project\PoluchitSpisokPolzovateleiProekta;
use App\Unitman\Business\Model\Account;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\ProjectList;
use App\Unitman\Business\ReadModel\ProjectUsersList;
use App\Unitman\Business\UseCase\Project\AddUserToProjectUseCase;
use App\Unitman\Business\UseCase\Project\PoluchitMoiProektiQuery;
use App\Unitman\Business\UseCase\Project\PoluchitSpisokPolzovateleiProektaQuery;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;

final class PoluchenieMoihProektovTest extends AbstractProjectUseCase
{
    #[Test]
    function poluchenie_moih_proektov()
    {
        $repoId = Uuid::uuid7()->toString();
        $adminId = Uuid::uuid7()->toString();
        $userId = Uuid::uuid7()->toString();
        $userId2 = Uuid::uuid7()->toString();
        $projectId = Uuid::uuid7()->toString();
        $projectId2 = Uuid::uuid7()->toString();
        $projectId3 = Uuid::uuid7()->toString();
        self::$container->set(CanGenerateGuid::class, new MemoryGuidGenerator([$repoId, $projectId, $projectId2, $projectId3]));

        $securityService = $this->getMockBuilder(UnitmanSecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        $securityService->expects($this->any())->method('getCurrentUserId')->willReturnOnConsecutiveCalls(
            $adminId,$adminId,$adminId, $userId
        );
        $securityService->expects($this->any())->method('getUserById')->willReturnOnConsecutiveCalls(
            new Account($userId, 'asd@asd.ru', false),
            new Account($userId2, 'asd2@asd.ru', false),
            new Account($userId, 'asd@asd.ru', false),
            new Account($userId2, 'asd2@asd.ru', false),
        );
        // Без этого стаба мок возвращает Account с неинициализированными
        // readonly-свойствами и проекция websocket-событий падает.
        $securityService->expects($this->any())->method('getSystemUser')->willReturn(new Account($adminId, 'asd@asd.ru', false));
        self::$container->set(UnitmanSecurityService::class, $securityService);

        $this->addRepoRaw(RepoType::GITLAB, 'repoName', 'http://repoUrl');
        $this->addProjectRaw($repoId, 'test', 'name', 'main');
        $this->addProjectRaw($repoId, 'test2', 'name2', 'main');
        $this->addProjectRaw($repoId, 'test3', 'name3', 'main');

        // Проекты активны сразу после создания; стадии сборки нет.

        $addUserUseCase = self::$container->get(AddUserToProjectUseCase::class);
        $command = new AddUserToProject($projectId, $userId);
        $addUserUseCase->handle($command);
        $command = new AddUserToProject($projectId, $userId2);
        $addUserUseCase->handle($command);

        $addUserUseCase = self::$container->get(AddUserToProjectUseCase::class);
        $command = new AddUserToProject($projectId2, $userId);
        $addUserUseCase->handle($command);

        $addUserUseCase = self::$container->get(AddUserToProjectUseCase::class);
        $command = new AddUserToProject($projectId3, $userId2);
        $addUserUseCase->handle($command);

        $query = self::$container->get(PoluchitMoiProektiQuery::class);
        /** @var PoluchitMoiProektiQuery $query*/
        $actualResult = $query->handle(new PoluchitMoiProekti());

        $actualIds = array_map(fn(array $projectList) => $projectList['id'], $actualResult);
        $expecteResultIds = [$projectId2, $projectId];
        $this->assertEquals($expecteResultIds, $actualIds);

        $polzovateliProekta = self::$container->get(PoluchitSpisokPolzovateleiProektaQuery::class);
        /** @var $polzovateliProekta PoluchitSpisokPolzovateleiProektaQuery*/

        $actualUsers = $polzovateliProekta->handle(new PoluchitSpisokPolzovateleiProekta($projectId));
        $expectedUsers = [new ProjectUsersList($adminId, 'ADMIN'), new ProjectUsersList($userId, 'USER'), new ProjectUsersList($userId2, 'USER')];
        $this->assertEquals($expectedUsers, $actualUsers);

        $actualUsers = $polzovateliProekta->handle(new PoluchitSpisokPolzovateleiProekta($projectId2));
        $expectedUsers = [new ProjectUsersList($adminId, 'ADMIN'), new ProjectUsersList($userId, 'USER')];
        $this->assertEquals($expectedUsers, $actualUsers);

        $actualUsers = $polzovateliProekta->handle(new PoluchitSpisokPolzovateleiProekta($projectId3));
        $expectedUsers = [new ProjectUsersList($adminId, 'ADMIN'), new ProjectUsersList($userId2, 'USER')];
        $this->assertEquals($expectedUsers, $actualUsers);
    }
}
