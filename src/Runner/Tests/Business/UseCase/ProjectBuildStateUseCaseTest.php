<?php

declare(strict_types=1);

namespace App\Runner\Tests\Business\UseCase;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\UseCase\ProjectBuildState\ProjectBuildStateInvalidPayloadException;
use App\Runner\Business\UseCase\ProjectBuildState\ProjectBuildStateInvalidProjectIdException;
use App\Runner\Business\UseCase\ProjectBuildStateInputGuard;
use App\Runner\Business\UseCase\ProjectBuildStateSignatureVerifier;
use App\Runner\Business\UseCase\ProveritSostoyanieSborkiProektaUseCase;
use App\Runner\Business\UseCase\RunnerSelectionService;
use App\Runner\Business\UseCase\ZapisatSostoyanieSborkiProektaUseCase;
use App\Runner\Infra\Adapter\DefaultRunnerSelector;
use App\Runner\Tests\Support\InMemoryProjectBuildStateRepository;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use App\Runner\Tests\Support\InMemoryUnitRunnerRepository;
use PHPUnit\Framework\TestCase;

final class ProjectBuildStateUseCaseTest extends TestCase
{
    private const RUNNER_ID = 'runner-1';
    private const PROJECT_ID = 'project-1';

    public function test_check_reports_not_built_when_record_absent(): void
    {
        $state = $this->checkUseCase($this->repository())->handle(self::RUNNER_ID, self::PROJECT_ID);

        self::assertNull($state);
    }

    public function test_record_then_check_returns_metadata(): void
    {
        $repository = $this->repository();

        $recorded = $this->recordUseCase($repository)->handle(
            self::RUNNER_ID,
            self::PROJECT_ID,
            builtAt: 1764000000,
            commit: 'a1b2c3d4',
            branch: 'main',
        );

        self::assertSame(self::RUNNER_ID, $recorded->runnerId);
        self::assertSame(self::PROJECT_ID, $recorded->projectId);
        self::assertSame(1764000000, $recorded->builtAt);

        $state = $this->checkUseCase($repository)->handle(self::RUNNER_ID, self::PROJECT_ID);

        self::assertNotNull($state);
        self::assertSame(1764000000, $state->builtAt);
        self::assertSame('a1b2c3d4', $state->commit);
        self::assertSame('main', $state->branch);
    }

    public function test_record_payload_defaults_built_at_to_now(): void
    {
        $before = time();

        $state = $this->recordUseCase($this->repository())->handlePayload(
            self::RUNNER_ID,
            self::PROJECT_ID,
            '{}',
        );

        self::assertGreaterThanOrEqual($before, $state->builtAt);
        self::assertNull($state->commit);
        self::assertNull($state->branch);
    }

    public function test_record_is_idempotent_for_same_runner_and_project(): void
    {
        $repository = $this->repository();
        $useCase = $this->recordUseCase($repository);

        $first = $useCase->handle(self::RUNNER_ID, self::PROJECT_ID, 100, 'aaa', 'main');
        $second = $useCase->handle(self::RUNNER_ID, self::PROJECT_ID, 200, 'bbb', 'dev');

        self::assertSame(1, $repository->count(), 'upsert не должен создавать дубли');
        self::assertSame(self::RUNNER_ID, $second->runnerId);
        self::assertSame(self::PROJECT_ID, $second->projectId);
        self::assertSame(200, $second->builtAt);
        self::assertSame('bbb', $second->commit);
        self::assertSame('dev', $second->branch);
        self::assertSame($first->runnerId, $second->runnerId);
    }

    public function test_recording_does_not_touch_other_runner_state(): void
    {
        $repository = $this->repository();
        $runners = new InMemoryRunnerRepository();
        $runners->save(RunnerState::sozdatRunner(self::RUNNER_ID, self::RUNNER_ID));
        $runners->save(RunnerState::sozdatRunner('runner-2', 'runner-2'));
        $useCase = $this->recordUseCase($repository, $runners);

        $useCase->handle(self::RUNNER_ID, self::PROJECT_ID, 100, 'aaa', 'main');
        $useCase->handle('runner-2', self::PROJECT_ID, 200, 'bbb', 'dev');

        self::assertSame(2, $repository->count());

        $first = $repository->find(self::RUNNER_ID, self::PROJECT_ID);
        self::assertNotNull($first);
        self::assertSame(100, $first->builtAt);
        self::assertSame('aaa', $first->commit);
    }

    public function test_unknown_runner_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('runner.not_found');

        $this->checkUseCase($this->repository())->handle('unknown-runner', self::PROJECT_ID);
    }

    public function test_inactive_runner_is_rejected(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save(new RunnerState('inactive-runner', 'inactive-runner', false));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('runner.inactive');

        $this->checkUseCase($this->repository(), $runners)
            ->handle('inactive-runner', self::PROJECT_ID);
    }

    public function test_invalid_project_id_is_rejected_on_check(): void
    {
        $this->expectException(ProjectBuildStateInvalidProjectIdException::class);
        $this->expectExceptionMessage('project_build_state.invalid_project_id');

        $this->checkUseCase($this->repository())->handle(self::RUNNER_ID, '   ');
    }

    public function test_invalid_project_id_is_rejected_on_record(): void
    {
        $this->expectException(ProjectBuildStateInvalidProjectIdException::class);
        $this->expectExceptionMessage('project_build_state.invalid_project_id');

        $this->recordUseCase($this->repository())->handle(self::RUNNER_ID, 'bad/id');
    }

    public function test_invalid_json_payload_is_rejected(): void
    {
        $this->expectException(ProjectBuildStateInvalidPayloadException::class);
        $this->expectExceptionMessage('project_build_state.invalid_payload');

        $this->recordUseCase($this->repository())->handlePayload(
            self::RUNNER_ID,
            self::PROJECT_ID,
            '{not json',
        );
    }

    public function test_non_integer_built_at_is_rejected(): void
    {
        $this->expectException(ProjectBuildStateInvalidPayloadException::class);
        $this->expectExceptionMessage('project_build_state.invalid_payload');

        $this->recordUseCase($this->repository())->handlePayload(
            self::RUNNER_ID,
            self::PROJECT_ID,
            '{"builtAt":"1764000000"}',
        );
    }

    public function test_non_string_commit_is_rejected(): void
    {
        $this->expectException(ProjectBuildStateInvalidPayloadException::class);
        $this->expectExceptionMessage('project_build_state.invalid_payload');

        $this->recordUseCase($this->repository())->handlePayload(
            self::RUNNER_ID,
            self::PROJECT_ID,
            '{"commit":123}',
        );
    }

    private function repository(): InMemoryProjectBuildStateRepository
    {
        return new InMemoryProjectBuildStateRepository();
    }

    private function selectionService(?InMemoryRunnerRepository $runners = null): RunnerSelectionService
    {
        $runners ??= new InMemoryRunnerRepository();

        if ($runners->findById(self::RUNNER_ID) === null) {
            $runners->save(RunnerState::sozdatRunner(self::RUNNER_ID, self::RUNNER_ID));
        }

        return new RunnerSelectionService(
            $runners,
            new DefaultRunnerSelector(),
            new InMemoryUnitRunnerRepository(),
        );
    }

    private function checkUseCase(
        InMemoryProjectBuildStateRepository $repository,
        ?InMemoryRunnerRepository $runners = null,
    ): ProveritSostoyanieSborkiProektaUseCase {
        return new ProveritSostoyanieSborkiProektaUseCase(
            $this->selectionService($runners),
            $repository,
            new ProjectBuildStateInputGuard(),
        );
    }

    private function recordUseCase(
        InMemoryProjectBuildStateRepository $repository,
        ?InMemoryRunnerRepository $runners = null,
    ): ZapisatSostoyanieSborkiProektaUseCase {
        return new ZapisatSostoyanieSborkiProektaUseCase(
            $this->selectionService($runners),
            $repository,
            new ProjectBuildStateInputGuard(),
        );
    }
}
