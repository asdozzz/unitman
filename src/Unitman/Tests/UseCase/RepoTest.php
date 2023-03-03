<?php

namespace App\Unitman\Tests\UseCase;

use App\Unitman\Business\Command\AddRepo;
use App\Unitman\Business\Command\ChangeCredentialsOfRepo;
use App\Unitman\Business\Command\CheckAccessToRepo;
use App\Unitman\Business\Command\DeleteRepo;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\RepoAdapter\CheckAccessResponse;
use App\Unitman\Business\Port\CanCheckAccessToRepo;
use App\Unitman\Business\Port\CanGeneateGuid;
use App\Unitman\Business\Port\SecurityService;
use App\Unitman\Business\UseCase\AddRepoUseCase;
use App\Unitman\Business\UseCase\ChangeCredentialsOfRepoUseCase;
use App\Unitman\Business\UseCase\CheckAccessToRepoUseCase;
use App\Unitman\Business\UseCase\DeleteRepoUseCase;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Repository\RepoListRepository;
use App\Utils\EventSauce\AbstractTestCaseWithTransactionWrapper;
use Ramsey\Uuid\Nonstandard\Uuid;

final class RepoTest extends AbstractTestCaseWithTransactionWrapper
{
    /**
     * @test
     * */
    function repo_was_deleted()
    {
        $repoId = Uuid::uuid7()->toString();
        $repoName = 'testRepo';

        $this->addRepo($repoId, $repoName, RepoType::GITHUB, Repo::GITHUB_REPO_URL);

        $deleteCommand = new DeleteRepo($repoId);

        $sut = self::$container->get(DeleteRepoUseCase::class);
        /** @var DeleteRepoUseCase $sut*/
        $sut->handle($deleteCommand);

        $repoListRepository = self::$container->get(RepoListRepository::class);
        /** @var RepoListRepository $repoListRepository*/
        $row = $repoListRepository->findRowById($repoId);

        $this->assertTrue(empty($row));
    }

    /**
     * @test
     * */
    function credentials_was_changed()
    {
        $repoId = Uuid::uuid7()->toString();
        $repoName = 'testRepo';

        $this->addRepo($repoId, $repoName, RepoType::GITLAB, 'https://org@gitlab.ru');

        $command = new ChangeCredentialsOfRepo($repoId, 'newLogin', 'newPassword','https://org2@gitlab.ru');
        $useCase = self::$container->get(ChangeCredentialsOfRepoUseCase::class);
        /** @var ChangeCredentialsOfRepoUseCase $useCase*/
        $useCase->handle($command);

        $repoListRepository = self::$container->get(RepoListRepository::class);
        /** @var RepoListRepository $repoListRepository*/
        $repoList = $repoListRepository->getById($repoId);

        $this->assertEquals(false, $repoList->isConfirmed());
        $this->assertEquals('newLogin', $repoList->getRepoLogin());
        $this->assertEquals('newPassword', $repoList->getRepoPassword());
        $this->assertEquals('https://org2@gitlab.ru', $repoList->getRepoUrl());
    }

    /**
     * @test
     * */
    function access_is_confirmed()
    {
        $repoId = Uuid::uuid7()->toString();
        $repoName = 'testRepo';

        $this->addRepo($repoId, $repoName, RepoType::GITLAB, 'https://org@gitlab.ru');

        $gitlabApi = $this->getMockBuilder(CanCheckAccessToRepo::class)->getMock();
        $gitlabApi->expects($this->any())->method('checkAccess')->willReturn(CheckAccessResponse::success());

        self::$container->set(CanCheckAccessToRepo::class, $gitlabApi);

        $command = new CheckAccessToRepo($repoId);
        $useCase = self::$container->get(CheckAccessToRepoUseCase::class);
        /** @var CheckAccessToRepoUseCase $useCase*/
        $useCase->handle($command);

        $repoListRepository = self::$container->get(RepoListRepository::class);
        /** @var RepoListRepository $repoListRepository*/
        $repoList = $repoListRepository->getById($repoId);

        $this->assertEquals(true, $repoList->isConfirmed());
    }

    /**
     * @param string $repoId
     * @param string $repoName
     * @return RepoListRepository|object|null
     * @throws \Exception
     */
    public function addRepo(string $repoId, string $repoName, RepoType $repoType, ?string $repoUrl): void
    {
        $securityService = $this->getMockBuilder(SecurityService::class)->getMock();
        $securityService->expects($this->any())->method('isAdmin')->willReturn(true);
        self::$container->set(SecurityService::class, $securityService);
        self::$container->set(CanGeneateGuid::class, new MemoryGuidGenerator([$repoId]));


        $addCommand = new AddRepo(
            $repoType->value,
            $repoName,
            'login',
            'password',
            $repoUrl
        );

        $addRepoUseCase = self::$container->get(AddRepoUseCase::class);
        /** @var AddRepoUseCase $addRepoUseCase */
        $addRepoUseCase->handle($addCommand);

        $repoListRepository = self::$container->get(RepoListRepository::class);
        /** @var RepoListRepository $repoListRepository */
        $repoList = $repoListRepository->getById($repoId);

        $this->assertEquals($repoId, $repoList->getId());
        $this->assertEquals($repoType->value, $repoList->getType());
        $this->assertEquals($repoName, $repoList->getName());
        $this->assertEquals('login', $repoList->getRepoLogin());
        $this->assertEquals('password', $repoList->getRepoPassword());
        $this->assertEquals($repoUrl, $repoList->getRepoUrl());
        $this->assertFalse($repoList->isConfirmed());
    }
}
