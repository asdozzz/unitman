<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Controller;

use App\Runner\Business\Model\RepoCredentialGrant;
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\UmeetPoluchatKredensialyRepozitoriya;
use App\Runner\Business\UseCase\ProjectBuildStateSignatureVerifier;
use App\Runner\Business\UseCase\RunnerSelectionService;
use App\Runner\Business\Utils\RunnerSecretGenerator;
use App\Runner\Infra\Adapter\DefaultRunnerSelector;
use App\Runner\Infra\Controller\RepoCredentialsController;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use App\Runner\Tests\Support\InMemoryUnitRunnerRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class RepoCredentialsControllerTest extends TestCase
{
    private const SECRET = 'broker-master-key';
    private const RUNNER_ID = 'runner-1';
    private const REPO_ID = 'repo-1';
    private const PROJECT_ID = 'project-1';

    public function test_valid_runner_gets_short_lived_grant_without_userinfo(): void
    {
        $body = $this->body();
        $response = $this->controller()->issue(
            $this->signedRequest($body),
            $this->verifier($this->runners()),
            $this->selection($this->runners()),
            $this->credentials(),
            new NullLogger(),
        );

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->decode($response);
        self::assertSame('https://git.example.com/org/repo.git', $payload['repoUrl']);
        self::assertStringNotContainsString('@', $payload['repoUrl']);
        self::assertStringNotContainsString('oauth2:', $payload['repoUrl']);
        self::assertSame('x-access-token', $payload['username']);
        self::assertSame('TESTTOKENEXAMPLEVALUE', $payload['token']);
        self::assertGreaterThan(time(), (int) $payload['expiresAt']);
        self::assertLessThanOrEqual(time() + 300, (int) $payload['expiresAt']);
    }

    public function test_wrong_signature_returns_401(): void
    {
        $body = $this->body();
        $response = $this->controller()->issue(
            $this->signedRequest($body, 'deadbeef'),
            $this->verifier($this->runners()),
            $this->selection($this->runners()),
            $this->credentials(),
            new NullLogger(),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('runner.invalid_signature', $this->errorCode($response));
    }

    public function test_user_session_without_runner_headers_returns_401(): void
    {
        $request = Request::create('/api/internal/repo/credentials', 'POST', [], [], [], [], $this->body());
        $request->headers->set('Authorization', 'Bearer user-session-token');

        $response = $this->controller()->issue(
            $request,
            $this->verifier($this->runners()),
            $this->selection($this->runners()),
            $this->credentials(),
            new NullLogger(),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('runner.invalid_signature', $this->errorCode($response));
    }

    public function test_unknown_runner_returns_404(): void
    {
        $body = $this->body();
        $response = $this->controller()->issue(
            $this->signedRequest($body, null, 'unknown-runner'),
            $this->verifier($this->runners()),
            $this->selection($this->runners()),
            $this->credentials(),
            new NullLogger(),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('runner.not_found', $this->errorCode($response));
    }

    public function test_inactive_runner_returns_409(): void
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save(new RunnerState('inactive-runner', 'inactive-runner', false));
        $body = $this->body();

        $response = $this->controller()->issue(
            $this->signedRequest($body, null, 'inactive-runner'),
            $this->verifier($runners),
            $this->selection($runners),
            $this->credentials(),
            new NullLogger(),
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('runner.inactive', $this->errorCode($response));
    }

    public function test_foreign_repo_is_denied_by_default(): void
    {
        $body = $this->body(repoId: 'repo-foreign');
        $response = $this->controller()->issue(
            $this->signedRequest($body),
            $this->verifier($this->runners()),
            $this->selection($this->runners()),
            $this->credentials(deniedRepoId: 'repo-foreign'),
            new NullLogger(),
        );

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('repo.credentials.denied', $this->errorCode($response));
        self::assertStringNotContainsString('TESTTOKENEXAMPLEVALUE', (string) $response->getContent());
    }

    public function test_undecryptable_token_returns_503_without_leak(): void
    {
        $response = $this->controller()->issue(
            $this->signedRequest($this->body()),
            $this->verifier($this->runners()),
            $this->selection($this->runners()),
            $this->credentials(unavailable: true),
            new NullLogger(),
        );

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('repo.credentials.unavailable', $this->errorCode($response));
    }

    private function controller(): RepoCredentialsController
    {
        return new RepoCredentialsController();
    }

    private function runners(): InMemoryRunnerRepository
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save(RunnerState::sozdatRunner(self::RUNNER_ID, self::RUNNER_ID));

        return $runners;
    }

    private function verifier(InMemoryRunnerRepository $runners): ProjectBuildStateSignatureVerifier
    {
        return new ProjectBuildStateSignatureVerifier($runners, RunnerSecretGenerator::fromMasterKey(self::SECRET));
    }

    private function selection(InMemoryRunnerRepository $runners): RunnerSelectionService
    {
        return new RunnerSelectionService(
            $runners,
            new DefaultRunnerSelector(),
            new InMemoryUnitRunnerRepository(),
        );
    }

    private function credentials(
        ?string $deniedRepoId = null,
        bool $unavailable = false,
    ): UmeetPoluchatKredensialyRepozitoriya {
        return new class($deniedRepoId, $unavailable) implements UmeetPoluchatKredensialyRepozitoriya {
            public function __construct(
                private readonly ?string $deniedRepoId,
                private readonly bool $unavailable,
            ) {
            }

            public function poluchitKredensialy(
                string $runnerId,
                string $repoId,
                string $projectId,
                ?string $unitId = null,
            ): RepoCredentialGrant {
                if ($this->unavailable) {
                    throw new \DomainException('repo.credentials.unavailable');
                }

                if ($this->deniedRepoId !== null && $repoId === $this->deniedRepoId) {
                    throw new \DomainException('repo.credentials.denied');
                }

                return new RepoCredentialGrant(
                    repoId: $repoId,
                    projectId: $projectId,
                    repoUrl: 'https://git.example.com/org/repo.git',
                    username: 'x-access-token',
                    token: 'TESTTOKENEXAMPLEVALUE',
                    expiresAt: time() + 300,
                );
            }
        };
    }

    private function body(
        string $repoId = self::REPO_ID,
        string $projectId = self::PROJECT_ID,
    ): string {
        return json_encode([
            'repoId' => $repoId,
            'projectId' => $projectId,
        ], JSON_THROW_ON_ERROR);
    }

    private function signedRequest(
        string $body,
        ?string $signature = null,
        string $runnerId = self::RUNNER_ID,
    ): Request {
        $signature ??= $this->sign($body, $runnerId);

        $request = Request::create('/api/internal/repo/credentials', 'POST', [], [], [], [], $body);
        $request->headers->set(ProjectBuildStateSignatureVerifier::RUNNER_ID_HEADER, $runnerId);
        $request->headers->set(ProjectBuildStateSignatureVerifier::HEADER_NAME, $signature);

        return $request;
    }

    private function sign(string $body, string $runnerId = self::RUNNER_ID): string
    {
        return hash_hmac('sha256', $body, hash_hmac('sha256', $runnerId, self::SECRET));
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
