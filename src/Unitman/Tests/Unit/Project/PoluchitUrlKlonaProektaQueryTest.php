<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Project;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatUrlKlonaProekta;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\UseCase\Project\PoluchitUrlKlonaProektaQuery;
use PHPUnit\Framework\TestCase;

final class PoluchitUrlKlonaProektaQueryTest extends TestCase
{
    public function testVozvrashchaetUrlKlonaSTokenom(): void
    {
        $project = Project::addProject(
            'p1',
            new AddProject('r1', 'services/api', 'api', 'main', 'https://api.example.test', memoryLimit: 512),
            'account-1',
        );
        $repo = Repo::addRepo(
            'r1',
            new AddRepo('GITLAB', 'repo', 'secret-token', 'https://gitlab.example.test'),
            'https://gitlab.example.test',
        );

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('getById')->with('p1')->willReturn($project);

        $repoRepository = $this->createMock(RepoRepository::class);
        $repoRepository->method('getById')->with('r1')->willReturn($repo);

        $urlKlona = $this->createMock(UmeetPoluchatUrlKlonaProekta::class);
        $urlKlona->method('poluchitUrlKlonaProekta')
            ->with($repo, $project)
            ->willReturn('https://oauth2:secret-token@gitlab.example.test/services/api.git');

        $query = new PoluchitUrlKlonaProektaQuery($projectRepository, $repoRepository, $urlKlona);

        self::assertSame(
            'https://oauth2:secret-token@gitlab.example.test/services/api.git',
            $query->handle('p1'),
        );
    }

    public function testOtsutstvuyushiyProektNeNaiden(): void
    {
        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('getById')->willThrowException(new \DomainException('project.not_found'));

        $repoRepository = $this->createMock(RepoRepository::class);
        $urlKlona = $this->createMock(UmeetPoluchatUrlKlonaProekta::class);

        $query = new PoluchitUrlKlonaProektaQuery($projectRepository, $repoRepository, $urlKlona);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('opencode.workspace.project_not_found');

        $query->handle('missing');
    }
}
