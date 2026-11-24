<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Project;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatSpisokProektovDlyPolzovatelya;
use App\Unitman\Business\Port\Project\UmeetPoluchatUrlKlonaProekta;
use App\Unitman\Business\Port\Project\UmeetProveryatPushVProekt;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\ReadModel\Project\ProektDlyaWorkspaceReadModel;
use App\Unitman\Business\UseCase\Project\PoluchitProektiDlyaWorkspaceQuery;
use App\Unitman\Business\Utils\IdePort;
use PHPUnit\Framework\TestCase;

final class PoluchitProektiDlyaWorkspaceQueryTest extends TestCase
{
    private const ACCOUNT_ID = 'account-1';

    public function testSohranyaetPoryadokIPolyaReadModeli(): void
    {
        $projects = [
            'p1' => $this->proekt('p1', 'r1', 'services/one', 'one'),
            'p2' => $this->proekt('p2', 'r2', 'services/two', 'two'),
        ];
        $repos = [
            'r1' => $this->repo('r1'),
            'r2' => $this->repo('r2'),
        ];

        $query = $this->query($projects, $repos, ['p1', 'p2'], ['p1' => true, 'p2' => false]);

        $result = $query->handle(['p2', 'p1'], self::ACCOUNT_ID);

        self::assertSame(['p2', 'p1'], array_map(
            static fn (ProektDlyaWorkspaceReadModel $model): string => $model->projectId,
            $result,
        ));
        self::assertSame('two', $result[0]->projectName);
        self::assertSame('services/two', $result[0]->projectCode);
        self::assertFalse($result[0]->canPush);
        self::assertTrue($result[1]->canPush);
        self::assertSame('https://clone.example/p2.git', $result[0]->storageUrl);
        self::assertSame('https://clone.example/p1.git', $result[1]->storageUrl);
        self::assertSame(IdePort::izOkruzheniya(), $result[0]->idePort);
    }

    public function testDublikatProektaVizivaetKonflikt(): void
    {
        $projects = ['p1' => $this->proekt('p1', 'r1')];
        $repos = ['r1' => $this->repo('r1')];

        $query = $this->query($projects, $repos, ['p1'], ['p1' => true]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.project_conflict');

        $query->handle(['p1', 'p1'], self::ACCOUNT_ID);
    }

    public function testNedostupniyProektNeNaiden(): void
    {
        $projects = ['p1' => $this->proekt('p1', 'r1')];
        $repos = ['r1' => $this->repo('r1')];

        $query = $this->query($projects, $repos, ['p1']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.project_not_found');

        $query->handle(['p2'], self::ACCOUNT_ID);
    }

    public function testDostupniyNoOtsutstvuyushiyVRepozitoriiProektNeNaiden(): void
    {
        $query = $this->query([], [], ['p1']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.project_not_found');

        $query->handle(['p1'], self::ACCOUNT_ID);
    }

    public function testPustoySpisokVozvrashchaetPustoyMassiv(): void
    {
        $query = $this->query([], [], []);

        self::assertSame([], $query->handle([], self::ACCOUNT_ID));
    }

    public function testMainBranchZapolnyaetsyaIzProekta(): void
    {
        $projects = ['p1' => $this->proekt('p1', 'r1', mainBranch: 'develop')];
        $repos = ['r1' => $this->repo('r1')];

        $query = $this->query($projects, $repos, ['p1']);

        $result = $query->handle(['p1'], self::ACCOUNT_ID);

        self::assertCount(1, $result);
        self::assertSame('develop', $result[0]->mainBranch);
    }

    public function testZashishennayaVetkaOtkidaetsyaDazheEsliZashishenTolkoOdinProekt(): void
    {
        $projects = [
            'p1' => $this->proekt('p1', 'r1'),
            'p2' => $this->proekt('p2', 'r2', zashishennieVetki: ['release/*']),
        ];
        $repos = [
            'r1' => $this->repo('r1'),
            'r2' => $this->repo('r2'),
        ];

        $query = $this->query($projects, $repos, ['p1', 'p2']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.branch_protected');

        $query->handle(['p1', 'p2'], self::ACCOUNT_ID, 'release/2024');
    }

    public function testPustayaVetkaNeProveryaetZashitu(): void
    {
        $projects = ['p1' => $this->proekt('p1', 'r1', zashishennieVetki: ['main'])];
        $repos = ['r1' => $this->repo('r1')];

        $query = $this->query($projects, $repos, ['p1']);

        $result = $query->handle(['p1'], self::ACCOUNT_ID);

        self::assertCount(1, $result);
    }

    public function testNezashishennayaNepustayaVetkaUspeh(): void
    {
        $projects = ['p1' => $this->proekt('p1', 'r1', zashishennieVetki: ['main', 'release/*'])];
        $repos = ['r1' => $this->repo('r1')];

        $query = $this->query($projects, $repos, ['p1']);

        $result = $query->handle(['p1'], self::ACCOUNT_ID, 'feature-test');

        self::assertCount(1, $result);
        self::assertSame('p1', $result[0]->projectId);
    }

    /**
     * @param string[] $zashishennieVetki
     */
    private function proekt(
        string $projectId,
        string $repoId,
        string $code = 'services/api',
        string $name = 'api',
        string $mainBranch = 'main',
        array $zashishennieVetki = [],
    ): Project {
        return Project::addProject(
            $projectId,
            new AddProject($repoId, $code, $name, $mainBranch,
                memoryLimit: 512,
                zashishennieVetki: $zashishennieVetki,
            ),
            self::ACCOUNT_ID,
        );
    }

    private function repo(string $repoId, RepoType $repoType = RepoType::GITLAB): Repo
    {
        return Repo::addRepo(
            $repoId,
            new AddRepo($repoType->value, 'repo', 'token', 'https://gitlab.example.test'),
            'https://gitlab.example.test',
        );
    }

    /**
     * @param array<string, Project> $projects
     * @param array<string, Repo> $repos
     * @param list<string> $availableProjectIds
     * @param array<string, bool> $canPushByProjectId
     */
    private function query(
        array $projects,
        array $repos,
        array $availableProjectIds,
        array $canPushByProjectId = [],
    ): PoluchitProektiDlyaWorkspaceQuery {
        $spisok = $this->createMock(UmeetPoluchatSpisokProektovDlyPolzovatelya::class);
        $spisok->method('poluchitSpisokProektovDlyPolzovatelya')
            ->with(self::ACCOUNT_ID)
            ->willReturn(array_map(
                static fn (string $projectId): array => ['id' => $projectId],
                $availableProjectIds,
            ));

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('getById')->willReturnCallback(
            static fn (string $projectId): Project => $projects[$projectId]
                ?? throw new \DomainException('project.not_found'),
        );

        $repoRepository = $this->createMock(RepoRepository::class);
        $repoRepository->method('getById')->willReturnCallback(
            static fn (string $repoId): Repo => $repos[$repoId]
                ?? throw new \DomainException('repo.not_found'),
        );

        $proverkaPusha = $this->createMock(UmeetProveryatPushVProekt::class);
        $proverkaPusha->method('canPush')->willReturnCallback(
            static fn (Repo $repo, Project $project): bool => $canPushByProjectId[$project->getId()] ?? false,
        );

        $urlKlona = $this->createMock(UmeetPoluchatUrlKlonaProekta::class);
        $urlKlona->method('poluchitUrlKlonaProekta')->willReturnCallback(
            static fn (Repo $repo, Project $project): string => 'https://clone.example/'.$project->getId().'.git',
        );

        return new PoluchitProektiDlyaWorkspaceQuery(
            $spisok,
            $projectRepository,
            $repoRepository,
            $proverkaPusha,
            $urlKlona,
        );
    }
}
