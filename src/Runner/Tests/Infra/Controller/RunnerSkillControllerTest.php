<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Controller;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Utils\RunnerSecretGenerator;
use App\Runner\Infra\Controller\RunnerSkillController;
use App\Runner\Infra\Service\RunnerCallbackSignatureChecker;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use App\Skill\Api\SkillApi;
use App\Skill\Business\Model\NavykOpenCode;
use App\Skill\Business\Utils\PostroenieEffektivnyhNavykov;
use App\Skill\Tests\Support\InMemoryNavykRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class RunnerSkillControllerTest extends TestCase
{
    private const SECRET = 'runner-skill-master-key';
    private const RUNNER_ID = 'runner-1';
    private const PROJECT_ID = 'aaaaaaaa-1111-2222-3333-444444444444';

    #[Test]
    public function valid_signature_returns_skills_with_manifest(): void
    {
        $response = $this->controller()->resolve(
            $this->signedRequest($this->body()),
            $this->checker($this->runners()),
            $this->skillApi(),
        );

        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decode($response);
        self::assertSame('success', $payload['status']);
        self::assertSame('global-global-skill', $payload['data']['skills'][0]['containerName']);
        self::assertSame(self::PROJECT_ID, $payload['data']['skills'][1]['projectId']);
        self::assertSame(64, strlen($payload['data']['skills'][0]['hash']));
        self::assertArrayHasKey('manifestHash', $payload['data']);
        self::assertSame(64, strlen($payload['data']['manifestHash']));
        self::assertGreaterThan(0, $payload['data']['totalBytes']);
        self::assertFalse($payload['data']['truncated']);
        self::assertStringNotContainsString(self::SECRET, (string) $response->getContent());
    }

    #[Test]
    public function wrong_signature_returns_401(): void
    {
        $response = $this->controller()->resolve(
            $this->signedRequest($this->body(), 'deadbeef'),
            $this->checker($this->runners()),
            $this->skillApi(),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('runner.invalid_signature', $this->errorCode($response));
    }

    #[Test]
    public function missing_runner_headers_return_401(): void
    {
        $request = Request::create('/api/internal/opencode/skills/resolve', 'POST', [], [], [], [], $this->body());

        $response = $this->controller()->resolve($request, $this->checker($this->runners()), $this->skillApi());

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('runner.invalid_signature', $this->errorCode($response));
    }

    #[Test]
    public function unknown_runner_returns_404(): void
    {
        $body = $this->body();
        $response = $this->controller()->resolve(
            $this->signedRequest($body, null, 'unknown-runner'),
            $this->checker($this->runners()),
            $this->skillApi(),
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('runner.not_found', $this->errorCode($response));
    }

    #[Test]
    public function unsupported_format_version_returns_400(): void
    {
        $body = $this->body(formatVersion: 2);
        $response = $this->controller()->resolve(
            $this->signedRequest($body),
            $this->checker($this->runners()),
            $this->skillApi(),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('skill.resolve.unsupported_format', $this->errorCode($response));
    }

    #[Test]
    public function oversized_max_total_bytes_returns_400(): void
    {
        $body = $this->body(maxTotalBytes: PostroenieEffektivnyhNavykov::MAX_TOTAL_BYTES + 1);
        $response = $this->controller()->resolve(
            $this->signedRequest($body),
            $this->checker($this->runners()),
            $this->skillApi(),
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function invalid_project_ids_return_400(): void
    {
        $body = json_encode([
            'projectIds' => [''],
            'formatVersion' => 1,
            'maxTotalBytes' => 524288,
        ], JSON_THROW_ON_ERROR);

        $response = $this->controller()->resolve(
            $this->signedRequest($body),
            $this->checker($this->runners()),
            $this->skillApi(),
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function manifest_hash_is_deterministic_and_truncates_by_bytes(): void
    {
        $skillApi = $this->skillApi();

        $full = $this->decode($this->controller()->resolve(
            $this->signedRequest($this->body(maxTotalBytes: 1000)),
            $this->checker($this->runners()),
            $skillApi,
        ));

        $small = $this->decode($this->controller()->resolve(
            $this->signedRequest($this->body(maxTotalBytes: 10)),
            $this->checker($this->runners()),
            $skillApi,
        ));

        self::assertSame($full['data']['manifestHash'], $full['data']['manifestHash']);
        self::assertTrue($small['data']['truncated']);
        self::assertLessThanOrEqual(10, $small['data']['totalBytes']);
    }

    private function controller(): RunnerSkillController
    {
        return new RunnerSkillController();
    }

    private function skillApi(): SkillApi
    {
        $repo = new InMemoryNavykRepository();
        $repo->create(NavykOpenCode::create(
            'g-1',
            'global-skill',
            'Global',
            '',
            'global content',
            true,
            [],
            'account-1',
            1000,
        ));
        $repo->create(NavykOpenCode::create(
            'p-1',
            'project-skill',
            'Project',
            '',
            'project content',
            false,
            [self::PROJECT_ID],
            'account-1',
            1000,
        ));

        return new SkillApi(new PostroenieEffektivnyhNavykov($repo));
    }

    private function runners(): InMemoryRunnerRepository
    {
        $runners = new InMemoryRunnerRepository();
        $runners->save(RunnerState::sozdatRunner(self::RUNNER_ID, self::RUNNER_ID));

        return $runners;
    }

    private function checker(InMemoryRunnerRepository $runners): RunnerCallbackSignatureChecker
    {
        return new RunnerCallbackSignatureChecker(
            $runners,
            RunnerSecretGenerator::fromMasterKey(self::SECRET),
        );
    }

    private function body(
        int $formatVersion = 1,
        int $maxTotalBytes = 524288,
    ): string {
        return json_encode([
            'projectIds' => [self::PROJECT_ID],
            'formatVersion' => $formatVersion,
            'maxTotalBytes' => $maxTotalBytes,
        ], JSON_THROW_ON_ERROR);
    }

    private function signedRequest(
        string $body,
        ?string $signature = null,
        string $runnerId = self::RUNNER_ID,
    ): Request {
        $signature ??= $this->sign($body, $runnerId);

        $request = Request::create('/api/internal/opencode/skills/resolve', 'POST', [], [], [], [], $body);
        $request->headers->set(RunnerCallbackSignatureChecker::RUNNER_ID_HEADER, $runnerId);
        $request->headers->set(RunnerCallbackSignatureChecker::HEADER_NAME, $signature);

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

        return (string) ($payload['code'] ?? $payload['data']['code'] ?? $payload['data']['message'] ?? '');
    }
}
