<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Controller;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\UseCase\ProjectBuildStateInputGuard;
use App\Runner\Business\UseCase\ProjectBuildStateSignatureVerifier;
use App\Runner\Business\UseCase\ProveritSostoyanieSborkiProektaUseCase;
use App\Runner\Business\UseCase\RunnerSelectionService;
use App\Runner\Business\UseCase\ZapisatSostoyanieSborkiProektaUseCase;
use App\Runner\Business\Utils\RunnerSecretGenerator;
use App\Runner\Infra\Adapter\DefaultRunnerSelector;
use App\Runner\Infra\Controller\ProjectBuildStateController;
use App\Runner\Tests\Support\InMemoryProjectBuildStateRepository;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use App\Runner\Tests\Support\InMemoryUnitRunnerRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ProjectBuildStateControllerTest extends TestCase
{
    private const SECRET = 'callback-master-key';
    private const RUNNER_ID = 'runner-1';
    private const PROJECT_ID = 'project-1';

    public function test_check_returns_not_built_with_nulls(): void
    {
        $runners = $this->runners();

        $response = $this->controller()->check(
            self::RUNNER_ID,
            self::PROJECT_ID,
            $this->signedRequest(''),
            $this->checkUseCase($this->repository(), $runners),
            $this->verifier($runners),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['built' => false, 'builtAt' => null, 'commit' => null, 'branch' => null],
            $this->decode($response),
        );
    }

    public function test_record_then_check_returns_built_state_and_metadata(): void
    {
        $repository = $this->repository();
        $runners = $this->runners();
        $controller = $this->controller();

        $body = json_encode(
            ['builtAt' => 1764000000, 'commit' => 'a1b2c3d4', 'branch' => 'main'],
            JSON_THROW_ON_ERROR,
        );

        $recordResponse = $controller->record(
            self::RUNNER_ID,
            self::PROJECT_ID,
            $this->signedRequest($body, $this->sign($body), 'PUT'),
            $this->recordUseCase($repository, $runners),
            $this->verifier($runners),
        );

        self::assertSame(200, $recordResponse->getStatusCode());
        self::assertSame(
            ['built' => true, 'builtAt' => 1764000000, 'commit' => 'a1b2c3d4', 'branch' => 'main'],
            $this->decode($recordResponse),
        );

        $checkResponse = $controller->check(
            self::RUNNER_ID,
            self::PROJECT_ID,
            $this->signedRequest(''),
            $this->checkUseCase($repository, $runners),
            $this->verifier($runners),
        );

        self::assertSame(200, $checkResponse->getStatusCode());
        self::assertSame(
            ['built' => true, 'builtAt' => 1764000000, 'commit' => 'a1b2c3d4', 'branch' => 'main'],
            $this->decode($checkResponse),
        );
    }

    public function test_missing_signature_returns_401(): void
    {
        $runners = $this->runners();

        $request = $this->signedRequest('');
        $request->headers->remove(ProjectBuildStateSignatureVerifier::HEADER_NAME);

        $response = $this->controller()->check(
            self::RUNNER_ID,
            self::PROJECT_ID,
            $request,
            $this->checkUseCase($this->repository(), $runners),
            $this->verifier($runners),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('runner.invalid_signature', $this->errorCode($response));
    }

    public function test_wrong_signature_returns_401(): void
    {
        $runners = $this->runners();

        $response = $this->controller()->check(
            self::RUNNER_ID,
            self::PROJECT_ID,
            $this->signedRequest('', 'deadbeef'),
            $this->checkUseCase($this->repository(), $runners),
            $this->verifier($runners),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('runner.invalid_signature', $this->errorCode($response));
    }

    public function test_runner_id_header_mismatch_returns_401(): void
    {
        $runners = $this->runners();

        $request = $this->signedRequest('', $this->sign(''));
        $request->headers->set(ProjectBuildStateSignatureVerifier::RUNNER_ID_HEADER, 'another-runner');

        $response = $this->controller()->check(
            self::RUNNER_ID,
            self::PROJECT_ID,
            $request,
            $this->checkUseCase($this->repository(), $runners),
            $this->verifier($runners),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('runner.invalid_signature', $this->errorCode($response));
    }

    public function test_invalid_project_id_returns_400(): void
    {
        $runners = $this->runners();

        $response = $this->controller()->check(
            self::RUNNER_ID,
            'bad/id',
            $this->signedRequest(''),
            $this->checkUseCase($this->repository(), $runners),
            $this->verifier($runners),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('project_build_state.invalid_project_id', $this->errorCode($response));
    }

    public function test_invalid_payload_returns_400(): void
    {
        $runners = $this->runners();
        $body = '{"builtAt":"not-an-int"}';

        $response = $this->controller()->record(
            self::RUNNER_ID,
            self::PROJECT_ID,
            $this->signedRequest($body, $this->sign($body), 'PUT'),
            $this->recordUseCase($this->repository(), $runners),
            $this->verifier($runners),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('project_build_state.invalid_payload', $this->errorCode($response));
    }

    public function test_unknown_runner_returns_404(): void
    {
        $runners = $this->runners();

        $response = $this->controller()->check(
            'unknown-runner',
            self::PROJECT_ID,
            $this->signedRequest('', $this->sign('', 'unknown-runner'), 'GET', 'unknown-runner'),
            $this->checkUseCase($this->repository(), $runners),
            $this->verifier($runners),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('runner.not_found', $this->errorCode($response));
    }

    public function test_inactive_runner_returns_409(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save(new RunnerState('inactive-runner', 'inactive-runner', false));

        $response = $this->controller()->check(
            'inactive-runner',
            self::PROJECT_ID,
            $this->signedRequest('', $this->sign('', 'inactive-runner'), 'GET', 'inactive-runner'),
            $this->checkUseCase($this->repository(), $runners),
            $this->verifier($runners),
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('runner.inactive', $this->errorCode($response));
    }

    private function controller(): ProjectBuildStateController
    {
        return new ProjectBuildStateController();
    }

    private function repository(): InMemoryProjectBuildStateRepository
    {
        return new InMemoryProjectBuildStateRepository();
    }

    private function runners(): InMemoryRunnerRepository
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save(RunnerState::sozdatRunner(self::RUNNER_ID, self::RUNNER_ID));

        return $runners;
    }

    private function verifier(InMemoryRunnerRepository $runners): ProjectBuildStateSignatureVerifier
    {
        return new ProjectBuildStateSignatureVerifier($runners, new RunnerSecretGenerator(self::SECRET));
    }

    private function selectionService(InMemoryRunnerRepository $runners): RunnerSelectionService
    {
        return new RunnerSelectionService(
            $runners,
            new DefaultRunnerSelector(),
            new InMemoryUnitRunnerRepository(),
        );
    }

    private function checkUseCase(
        InMemoryProjectBuildStateRepository $repository,
        InMemoryRunnerRepository $runners,
    ): ProveritSostoyanieSborkiProektaUseCase {
        return new ProveritSostoyanieSborkiProektaUseCase(
            $this->selectionService($runners),
            $repository,
            new ProjectBuildStateInputGuard(),
        );
    }

    private function recordUseCase(
        InMemoryProjectBuildStateRepository $repository,
        InMemoryRunnerRepository $runners,
    ): ZapisatSostoyanieSborkiProektaUseCase {
        return new ZapisatSostoyanieSborkiProektaUseCase(
            $this->selectionService($runners),
            $repository,
            new ProjectBuildStateInputGuard(),
        );
    }

    private function sign(string $body, string $runnerId = self::RUNNER_ID): string
    {
        return hash_hmac('sha256', $body, hash_hmac('sha256', $runnerId, self::SECRET));
    }

    private function signedRequest(
        string $body,
        ?string $signature = null,
        string $method = 'GET',
        string $runnerId = self::RUNNER_ID,
    ): Request {
        if ($signature === null) {
            $signature = $this->sign($body, $runnerId);
        }

        $request = Request::create(
            '/api/runner/' . $runnerId . '/projects/' . self::PROJECT_ID . '/build-state',
            $method,
            [],
            [],
            [],
            [],
            $body,
        );

        $request->headers->set(ProjectBuildStateSignatureVerifier::RUNNER_ID_HEADER, $runnerId);
        $request->headers->set(ProjectBuildStateSignatureVerifier::HEADER_NAME, $signature);

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        return json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function errorCode(JsonResponse $response): string
    {
        $payload = $this->decode($response);

        return (string) ($payload['code'] ?? $payload['data']['code'] ?? '');
    }
}
