<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\LichniyToken;

use App\Unitman\Business\Command\LichniyToken\DobavitLichniyToken;
use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Model\LichniyToken;
use App\Unitman\Business\Model\LichniyToken\ObyomPrav;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\CanGenerateGuid;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenListRepository;
use App\Unitman\Business\Port\LichniyToken\LichniyTokenRepository;
use App\Unitman\Business\Port\Project\CanGetProjectList;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetProveryatPushTokenom;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Repo\UmeetProveryatTokenHranilisha;
use App\Unitman\Business\ReadModel\LichniyToken\LichniyTokenList;
use App\Unitman\Business\ReadModel\ProjectList;
use App\Unitman\Business\ReadModel\ProjectList\NastroikiHukaProekta;
use App\Unitman\Business\ReadModel\ProjectList\ProjectListStateType;
use App\Unitman\Business\UseCase\LichniyToken\DobavitLichniyTokenUseCase;
use App\Unitman\Business\UseCase\LichniyToken\OpredelitelPravTokena;
use App\Unitman\Infra\Adapter\MemoryGuidGenerator;
use App\Unitman\Infra\Adapter\PersonalTokenOpenSslShifr;
use App\Unitman\Tests\Support\FakeUnitmanSecurityService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DobavitLichniyTokenUseCaseTest extends TestCase
{
    private const REPO_ID = 'repo-1';

    private const ACCOUNT_ID = 'account-1';

    #[Test]
    public function prava_opredelyayutsya_po_aktivnim_proektam_i_validacii_tokena(): void
    {
        $project1 = 'project-1';
        $project2 = 'project-2';

        $repo = Repo::addRepo(
            self::REPO_ID,
            new AddRepo(RepoType::GITLAB->value, 'repo', 'repo-token', 'https://gitlab.local'),
            'https://gitlab.local',
        );
        $repo->accessConfirm();

        $projects = [
            $project1 => $this->project($project1, 'group/proj-one'),
            $project2 => $this->project($project2, 'group/proj-two'),
        ];

        $tokenCheck = new class implements UmeetProveryatTokenHranilisha {
            public bool $valid = true;

            public function proveritToken(string $token, Repo $repo): bool
            {
                return $this->valid;
            }
        };

        $pushCheck = new class implements UmeetProveryatPushTokenom {
            /** @var array<string, bool> */
            public array $allowed = [];

            public function canPushTokenom(string $token, Repo $repo, Project $project): bool
            {
                return $this->allowed[$project->getId()] ?? false;
            }
        };

        $store = new LichniyTokenTestStore();
        $aggregateRepository = new InMemoryLichniyTokenRepository($store);
        $listRepository = new InMemoryLichniyTokenListRepository($store);

        $guidGenerator = new MemoryGuidGenerator(['token-partial', 'token-no-rights']);

        $useCase = new DobavitLichniyTokenUseCase(
            new FakeUnitmanSecurityService(self::ACCOUNT_ID, false),
            new InMemoryRepoRepository($repo),
            $guidGenerator,
            $aggregateRepository,
            $listRepository,
            new PersonalTokenOpenSslShifr(base64_encode('test-only-personal-token-key-32b')),
            new OpredelitelPravTokena(
                new InMemoryProjectList([$projects[$project1], $projects[$project2]]),
                new InMemoryProjectsRepository($projects),
                $pushCheck,
            ),
            $tokenCheck,
        );

        // Часть проектов доступна — объём прав "spisok".
        $pushCheck->allowed = [$project1 => true];

        $partial = $useCase->handle(new DobavitLichniyToken(self::REPO_ID, 'glpat-secret-1234', 'Мой GitLab'));

        self::assertSame('token-partial', $partial->id);
        self::assertSame(ObyomPrav::Spisok->value, $partial->obyomPrav);
        self::assertSame([$project1], $partial->proektySPushom);
        self::assertFalse($partial->bezPravNaRedaktirovanie);
        self::assertSame('****1234', $partial->tokenHint);

        $stored = $listRepository->getById('token-partial');
        self::assertNotSame('glpat-secret-1234', $stored->tokenCipher);

        $shifr = new PersonalTokenOpenSslShifr(base64_encode('test-only-personal-token-key-32b'));
        self::assertSame(
            'glpat-secret-1234',
            $shifr->rasshifrovat($stored->tokenCipher, LichniyToken::makeAad(self::ACCOUNT_ID, 'token-partial')),
        );

        // Ни один проект не доступен — токен сохраняется с предупреждением.
        $pushCheck->allowed = [];

        $noRights = $useCase->handle(new DobavitLichniyToken(self::REPO_ID, 'glpat-no-rights', 'Без прав'));

        self::assertSame('token-no-rights', $noRights->id);
        self::assertSame(ObyomPrav::Spisok->value, $noRights->obyomPrav);
        self::assertSame([], $noRights->proektySPushom);
        self::assertTrue($noRights->bezPravNaRedaktirovanie);

        // Невалидный токен отклоняется до сохранения.
        $tokenCheck->valid = false;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('lichniy_token.ne_validen');

        $useCase->handle(new DobavitLichniyToken(self::REPO_ID, 'bad-token', 'Токен'));
    }

    private function project(string $id, string $code): Project
    {
        return Project::addProject(
            $id,
            new AddProject(self::REPO_ID, $code, 'proj', 'master', 'https://proxy.local', 300),
            self::ACCOUNT_ID,
        );
    }
}

/**
 * Общее состояние in-memory репозиториев токенов.
 */
final class LichniyTokenTestStore
{
    /** @var array<string, LichniyToken> */
    public array $aggregates = [];

    /** @var array<string, LichniyTokenList> */
    public array $readModels = [];
}

final class InMemoryLichniyTokenRepository implements LichniyTokenRepository
{
    public function __construct(private LichniyTokenTestStore $store)
    {
    }

    public function getById(string $id): LichniyToken
    {
        $token = $this->findById($id);

        if ($token === null) {
            throw new \DomainException('lichniy_token.ne_nayden');
        }

        return $token;
    }

    public function findById(string $id): ?LichniyToken
    {
        return $this->store->aggregates[$id] ?? null;
    }

    public function save(LichniyToken $token): void
    {
        $this->store->aggregates[$token->getId()] = $token;

        $this->store->readModels[$token->getId()] = new LichniyTokenList(
            $token->getId(),
            $token->getAccountId(),
            $token->getRepoId(),
            $token->getNazvanie(),
            $token->getTokenCipher(),
            $token->getTokenHint(),
            $token->getObyomPrav()->value,
            $token->getProektySPushom(),
            $token->getProverenoV(),
            $token->isDeleted(),
        );
    }
}

final class InMemoryLichniyTokenListRepository implements LichniyTokenListRepository
{
    public function __construct(private LichniyTokenTestStore $store)
    {
    }

    public function getById(string $id): LichniyTokenList
    {
        $token = $this->findById($id);

        if ($token === null) {
            throw new \DomainException('lichniy_token.ne_nayden');
        }

        return $token;
    }

    public function findById(string $id): ?LichniyTokenList
    {
        return $this->store->readModels[$id] ?? null;
    }

    public function findByAccountId(string $accountId): array
    {
        return array_values(array_filter(
            $this->store->readModels,
            static fn (LichniyTokenList $token): bool => $token->accountId === $accountId,
        ));
    }

    public function findByAccountIdAndRepoId(string $accountId, string $repoId): array
    {
        return array_values(array_filter(
            $this->store->readModels,
            static fn (LichniyTokenList $token): bool => $token->accountId === $accountId
                && $token->repoId === $repoId,
        ));
    }

    public function findAll(): array
    {
        return array_values($this->store->readModels);
    }
}

final class InMemoryRepoRepository implements RepoRepository
{
    public function __construct(private Repo $repo)
    {
    }

    public function getById(string $repoId): Repo
    {
        return $this->repo;
    }

    public function save(Repo $repo): void
    {
    }
}

final class InMemoryProjectsRepository implements ProjectRepository
{
    /**
     * @param array<string, Project> $projects
     */
    public function __construct(private array $projects)
    {
    }

    public function getById(string $id): Project
    {
        if (!isset($this->projects[$id])) {
            throw new \DomainException('project.not_found');
        }

        return $this->projects[$id];
    }

    public function save(Project $project): void
    {
    }
}

final class InMemoryProjectList implements CanGetProjectList
{
    /**
     * @param Project[] $projects
     */
    public function __construct(private array $projects)
    {
    }

    public function getList(\App\Unitman\Business\Command\Project\GetProjectList $query): array
    {
        return $this->projectsList();
    }

    public function getListByRepoId(string $repoId): array
    {
        return $this->projectsList();
    }

    /**
     * @return ProjectList[]
     */
    private function projectsList(): array
    {
        return array_map(
            static fn (Project $project): ProjectList => new ProjectList(
                $project->getId(),
                $project->getRepoId(),
                (string) $project->getCode(),
                (string) $project->getName(),
                $project->getMainBranchName(),
                true,
                ProjectListStateType::BUILD_SUCCESS,
                new NastroikiHukaProekta(false, false, false),
            ),
            $this->projects,
        );
    }
}
