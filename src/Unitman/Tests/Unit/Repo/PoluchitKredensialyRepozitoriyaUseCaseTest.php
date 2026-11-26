<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Repo;

use App\Unitman\Business\Command\Project\AddProject;
use App\Unitman\Business\Model\Project;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Port\Project\ProjectRepository;
use App\Unitman\Business\Port\Project\UmeetPoluchatUrlKlonaProekta;
use App\Unitman\Business\Port\Repo\RepoRepository;
use App\Unitman\Business\Port\Unit\UnitRepository;
use App\Unitman\Business\Port\Unit\UnitRunnerRepository;
use App\Unitman\Business\Port\UmeetShifrovatSekrety;
use App\Unitman\Business\UseCase\Repo\PoluchitKredensialyRepozitoriyaUseCase;
use PHPUnit\Framework\TestCase;

final class PoluchitKredensialyRepozitoriyaUseCaseTest extends TestCase
{
    private const RUNNER_ID = 'runner-1';
    private const REPO_ID = 'repo-1';
    private const PROJECT_ID = 'project-1';
    private const TOKEN = 'TESTTOKENEXAMPLEVALUE';

    public function test_returns_clean_url_and_explicit_username(): void
    {
        $grant = $this->useCase()->handle(self::RUNNER_ID, self::REPO_ID, self::PROJECT_ID);

        self::assertSame(self::REPO_ID, $grant->repoId);
        self::assertSame('https://gitlab.example/services/api.git', $grant->repoUrl);
        self::assertStringNotContainsString('@', $grant->repoUrl);
        self::assertStringNotContainsString('oauth2:', $grant->repoUrl);
        self::assertSame('oauth2', $grant->username);
        self::assertSame(self::TOKEN, $grant->token);
        self::assertGreaterThan(time(), $grant->expiresAt);
        self::assertLessThanOrEqual(time() + 300, $grant->expiresAt);
    }

    public function test_project_from_another_repo_is_denied(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('repo.credentials.denied');

        $this->useCase(projectRepoId: 'repo-other')->handle(
            self::RUNNER_ID,
            self::REPO_ID,
            self::PROJECT_ID,
        );
    }

    public function test_undecryptable_token_fails_closed(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('repo.credentials.unavailable');

        $this->useCase(decryptFails: true)->handle(
            self::RUNNER_ID,
            self::REPO_ID,
            self::PROJECT_ID,
        );
    }

    public function test_missing_ids_are_invalid_request(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('repo.credentials.invalid_request');

        $this->useCase()->handle(self::RUNNER_ID, '', self::PROJECT_ID);
    }

    private function useCase(
        string $projectRepoId = self::REPO_ID,
        bool $decryptFails = false,
    ): PoluchitKredensialyRepozitoriyaUseCase {
        $repo = Repo::addRepo(
            self::REPO_ID,
            RepoType::GITLAB->value,
            'repo',
            'https://gitlab.example',
            'cipher',
            '',
        );

        $project = Project::addProject(
            self::PROJECT_ID,
            new AddProject($projectRepoId, 'services/api', 'api', 'main', 512),
            'account-1',
        );

        $repos = $this->createMock(RepoRepository::class);
        $repos->method('getById')->willReturnCallback(
            static fn (string $repoId): Repo => $repoId === self::REPO_ID
                ? $repo
                : throw new \DomainException('repo.not_found'),
        );

        $projects = $this->createMock(ProjectRepository::class);
        $projects->method('getById')->willReturn($project);

        $units = $this->createMock(UnitRepository::class);
        $bindings = $this->createMock(UnitRunnerRepository::class);

        $url = $this->createMock(UmeetPoluchatUrlKlonaProekta::class);
        $url->method('poluchitUrlKlonaProekta')->willReturn('https://gitlab.example/services/api.git');

        $shifr = $this->createMock(UmeetShifrovatSekrety::class);
        if ($decryptFails) {
            $shifr->method('rasshifrovat')->willThrowException(new \DomainException('decrypt failed'));
        } else {
            $shifr->method('rasshifrovat')->willReturn(self::TOKEN);
        }

        return new PoluchitKredensialyRepozitoriyaUseCase(
            $repos,
            $projects,
            $units,
            $bindings,
            $url,
            $shifr,
        );
    }
}
