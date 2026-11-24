<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Project;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Project\SozdatVetkuProekta;
use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\Project\UmeetPoluchatTokenDlyaProekta;
use App\Unitman\Business\Port\Project\UmeetSozdavatVetkuProekta;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\UseCase\Project\SozdatVetkuProektaUseCase;
use App\Unitman\Tests\Support\FakeUnitmanSecurityService;
use App\Unitman\Tests\Support\InMemoryProjectRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SozdatVetkuProektaUseCaseTest extends TestCase
{
    private const PROJECT_ID = 'project-1';

    private const REPO_ID = 'repo-1';

    private const PROJECT_CODE = 'group/project-1';

    private const ACCOUNT_ID = 'account-1';

    #[Test]
    public function uspeshno_sozdaet_vetku_i_vozvrashchaet_imya(): void
    {
        [$useCase, $storage, $tokenResolver] = $this->useCase(null, null);

        $name = $useCase->handle(new SozdatVetkuProekta(' ' . self::PROJECT_ID . ' ', ' feature/new ', ' main '));

        self::assertSame('feature/new', $name);
        self::assertSame(self::PROJECT_ID, $tokenResolver->projectId);
        self::assertSame(self::ACCOUNT_ID, $tokenResolver->accountId);
        self::assertSame([[
            'projectCode' => self::PROJECT_CODE,
            'nazvanie' => 'feature/new',
            'otVetki' => 'main',
            'token' => 'personal-token',
        ]], $storage->calls);
    }

    #[Test]
    public function otsutstvie_tokena_perevoditsya_v_oshibku_proekta(): void
    {
        [$useCase] = $this->useCase(new \DomainException('unit.redaktor.net_lichnogo_tokena'), null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('project.net_lichnogo_tokena');

        $useCase->handle(new SozdatVetkuProekta(self::PROJECT_ID, 'feature/new', 'main'));
    }

    #[Test]
    public function token_bez_prav_perevoditsya_v_oshibku_vetki(): void
    {
        [$useCase] = $this->useCase(new \DomainException('unit.redaktor.token_bez_prav_na_proekt'), null);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('project.branch_bez_prav');

        $useCase->handle(new SozdatVetkuProekta(self::PROJECT_ID, 'feature/new', 'main'));
    }

    #[Test]
    public function sushchestvuyushchaya_vetka_perevoditsya_v_branch_exists(): void
    {
        [$useCase] = $this->useCase(null, new \RuntimeException('Reference already exists'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('project.branch_exists');

        $useCase->handle(new SozdatVetkuProekta(self::PROJECT_ID, 'feature/new', 'main'));
    }

    #[Test]
    public function nedostup_k_hranilishchu_perevoditsya_v_branch_bez_prav(): void
    {
        [$useCase] = $this->useCase(null, new \RuntimeException('HTTP 403 Forbidden'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('project.branch_bez_prav');

        $useCase->handle(new SozdatVetkuProekta(self::PROJECT_ID, 'feature/new', 'main'));
    }

    #[Test]
    public function nekorektnoe_imya_vetki_otklonyaetsya_bez_obrashcheniya_v_hranilishche(): void
    {
        [$useCase, $storage] = $this->useCase(null, null);

        try {
            $useCase->handle(new SozdatVetkuProekta(self::PROJECT_ID, 'feature name', 'main'));
            self::fail('Ожидалась ошибка некорректного имени ветки');
        } catch (\DomainException $exception) {
            self::assertSame('project.branch_invalid', $exception->getMessage());
        }

        self::assertSame([], $storage->calls);
    }

    #[Test]
    public function pustaya_bazovaya_vetka_otklonyaetsya(): void
    {
        [$useCase, $storage] = $this->useCase(null, null);

        try {
            $useCase->handle(new SozdatVetkuProekta(self::PROJECT_ID, 'feature/new', ' '));
            self::fail('Ожидалась ошибка пустой базовой ветки');
        } catch (\DomainException $exception) {
            self::assertSame('project.branch_invalid', $exception->getMessage());
        }

        self::assertSame([], $storage->calls);
    }

    /**
     * @return array{0: SozdatVetkuProektaUseCase, 1: object, 2: object}
     */
    private function useCase(?\Throwable $tokenError, ?\Throwable $storageError): array
    {
        $project = Project::addProject(
            self::PROJECT_ID,
            new AddProject(self::REPO_ID, self::PROJECT_CODE, 'project-one', 'main', 300),
            self::ACCOUNT_ID,
        );

        $projects = new InMemoryProjectRepository();
        $projects->save($project);

        $repo = Repo::addRepo(
            self::REPO_ID,
            new AddRepo(RepoType::GITLAB->value, 'repo', 'repo-token', 'https://gitlab.local'),
            'https://gitlab.local',
        );

        $repoRepository = new class($repo) implements RepoRepository {
            public function __construct(private readonly Repo $repo)
            {
            }

            public function getById(string $repoId): Repo
            {
                return $this->repo;
            }

            public function save(Repo $repo): void
            {
            }
        };

        $tokenResolver = new class($tokenError) implements UmeetPoluchatTokenDlyaProekta {
            public ?string $projectId = null;

            public ?string $accountId = null;

            public function __construct(private readonly ?\Throwable $error)
            {
            }

            public function poluchitTokenDlyProekta(string $projectId, string $accountId): string
            {
                $this->projectId = $projectId;
                $this->accountId = $accountId;

                if ($this->error !== null) {
                    throw $this->error;
                }

                return 'personal-token';
            }
        };

        $storage = new class($storageError) implements UmeetSozdavatVetkuProekta {
            /**
             * @var array<int, array{projectCode: string, nazvanie: string, otVetki: string, token: string}>
             */
            public array $calls = [];

            public function __construct(private readonly ?\Throwable $error)
            {
            }

            public function sozdatVetkuVHranilishe(
                Repo $repo,
                string $projectCode,
                string $nazvanieVetki,
                string $otVetki,
                string $token,
            ): void {
                $this->calls[] = [
                    'projectCode' => $projectCode,
                    'nazvanie' => $nazvanieVetki,
                    'otVetki' => $otVetki,
                    'token' => $token,
                ];

                if ($this->error !== null) {
                    throw $this->error;
                }
            }
        };

        $useCase = new SozdatVetkuProektaUseCase(
            $projects,
            $repoRepository,
            new FakeUnitmanSecurityService(self::ACCOUNT_ID),
            $tokenResolver,
            $storage,
        );

        return [$useCase, $storage, $tokenResolver];
    }
}
