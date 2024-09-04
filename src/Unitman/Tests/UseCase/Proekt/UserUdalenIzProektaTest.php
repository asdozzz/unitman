<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\AddUserToProject;
use App\Unitman\Business\Command\Project\RemoveUserFromProject;
use App\Unitman\Business\ReadModel\ProjectUsersList;
use App\Unitman\Business\UseCase\Project\AddUserToProjectUseCase;
use App\Unitman\Business\UseCase\Project\RemoveUserFromProjectUseCase;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use Ramsey\Uuid\Uuid;

final class UserUdalenIzProektaTest extends AbstractProjectUseCase
{
    /**
     * @test
     * */
    function user_udalen_is_proekta()
    {
        $repoId = Uuid::uuid7()->toString();
        $adminId = Uuid::uuid7()->toString();
        $userId = Uuid::uuid7()->toString();
        $userId2 = Uuid::uuid7()->toString();
        $projectId = $this->addProject($repoId, 'asdozzz/unitman', 'Units', 'main', 'thttp://testcase.su', $adminId);

        $addUserUseCase = self::$container->get(AddUserToProjectUseCase::class);
        $command = new AddUserToProject($projectId, $userId);
        $addUserUseCase->handle($command);

        $command = new AddUserToProject($projectId, $userId2);
        $addUserUseCase->handle($command);

        $projectRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectRepo SqlProjectListRepository*/
        $project = $projectRepo->getById($projectId);
        $expectedUsers = [new ProjectUsersList($adminId, 'ADMIN'), new ProjectUsersList($userId, 'USER'), new ProjectUsersList($userId2, 'USER')];
        $this->assertEquals($expectedUsers, $project->users);

        $removeCommand = new RemoveUserFromProject($projectId, $userId);
        $deleteUseCase = self::$container->get(RemoveUserFromProjectUseCase::class);
        $deleteUseCase->handle($removeCommand);

        $project = $projectRepo->getById($projectId);

        $expectedUsers = [new ProjectUsersList($adminId, 'ADMIN'),new ProjectUsersList($userId2, 'USER')];
        $this->assertEquals($expectedUsers, $project->users);
    }
}
