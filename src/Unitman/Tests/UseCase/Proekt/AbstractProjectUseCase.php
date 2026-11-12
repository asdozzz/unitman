<?php

namespace App\Unitman\Tests\UseCase\Proekt;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\UnitmanSecurityService;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Business\ReadModel\ProjectUsersList;
use App\Unitman\Business\UseCase\Project\AddProjectUseCase;
use App\Unitman\Business\UseCase\Repo\AddRepoUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\Project\SqlProjectListRepository;
use App\Unitman\Tests\UseCase\AbstractUnitmanUseCase;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use Ramsey\Uuid\Uuid;

class AbstractProjectUseCase extends AbstractUnitmanUseCase
{
    /**
     * Тесты работают на реальной БД приложения (см. .env.test / doctrine.yaml),
     * поэтому жёстко заданные код и имя проекта могли совпасть с уже существующим
     * проектом в dev-БД, и AddProjectUseCase падал с DomainException('project.double').
     * Уникальные значения гарантируют независимость теста от содержимого БД.
     */
    protected function poluchitUnikalnyiKodProekta(): string
    {
        return 'unitman-tests/' . Uuid::uuid7()->toString();
    }

    protected function poluchitUnikalnoeImyaProekta(): string
    {
        return 'UnitmanTestsProject-' . Uuid::uuid7()->toString();
    }

    function addProject(string $repoId,string $projectCode, string $projectName, string $mainBranch, string $proxyHost, string $userId): string
    {
        $projectId = Uuid::uuid7()->toString();
        self::$container->set(CanGenerateGuid::class, new MemoryGuidGenerator([$projectId]));

        $this->addProjectRaw($repoId, $projectCode, $projectName, $mainBranch, $proxyHost);

        $projectListRepo = self::$container->get(SqlProjectListRepository::class);
        /** @var $projectListRepo SqlProjectListRepository*/
        $project = $projectListRepo->getById($projectId);

        $this->assertEquals($project->repoId, $repoId);
        $this->assertEquals($project->id, $projectId);
        $this->assertEquals($project->code, $projectCode);
        $this->assertEquals($project->name, $projectName);
        $this->assertEquals($project->mainBranch, $mainBranch);
        $this->assertEquals($project->isActive, false);
        $this->assertEquals($project->state, ProjectListStateType::NEW);
        $this->assertEquals($project->proxyHost, $proxyHost);
        $this->assertEquals($project->users, [new ProjectUsersList($userId, 'ADMIN')]);

        return $projectId;
    }
}
