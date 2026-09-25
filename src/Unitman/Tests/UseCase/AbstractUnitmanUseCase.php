<?php

namespace App\Unitman\Tests\UseCase;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\UseCase\Project\AddProjectUseCase;
use App\Unitman\Business\UseCase\Repo\AddRepoUseCase;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;

class AbstractUnitmanUseCase extends AbstractTestCaseWithTransactionWrapper
{
    /**
     * @param RepoType $repoType
     * @param string $repoName
     * @param string|null $repoUrl
     * @return void
     * @throws \Exception
     */
    protected function addRepoRaw(RepoType $repoType, string $repoName, ?string $repoUrl): void
    {
        $addCommand = new AddRepo(
            $repoType->value,
            $repoName,
            'token',
            $repoUrl
        );

        $addRepoUseCase = self::$container->get(\App\Unitman\Business\UseCase\Repo\AddRepoUseCase::class);
        /** @var AddRepoUseCase $addRepoUseCase */
        $addRepoUseCase->handle($addCommand);
    }
    /**
     * @param string $repoId
     * @param string $projectCode
     * @param string $projectName
     * @param string $mainBranch
     * @param string $proxyHost
     * @return string
     * @throws \Exception
     */
    protected function addProjectRaw(string $repoId, string $projectCode, string $projectName, string $mainBranch, string $proxyHost): void
    {
        $command = new AddProject($repoId, $projectCode, $projectName, $mainBranch, $proxyHost, memoryLimit: 300);
        $useCase = self::$container->get(AddProjectUseCase::class);
        $useCase->handle($command);
    }
}
