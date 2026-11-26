<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Controller;

use App\Runner\Business\UseCase\RegisterRunnerUseCase;
use App\Runner\Business\Utils\RunnerIdGenerator;
use App\Runner\Business\Utils\RunnerSecretGenerator;
use App\Runner\Infra\Controller\RunnerController;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class RunnerControllerRegisterTest extends TestCase
{
    private const MASTER_KEY = 'registration-master-key';
    private const REGISTRY_HOST = 'registry.example.com';

    public function test_valid_request_returns_201_and_exact_v2_payload(): void
    {
        $body = json_encode([
            'hostname' => 'runner-1.example.com',
            'version' => '1.4.2',
        ], JSON_THROW_ON_ERROR);

        $response = $this->controller()->register(
            $this->request($body),
            $this->useCase($this->repository()),
        );

        self::assertSame(201, $response->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['runnerId', 'taskQueue', 'secret', 'registryHost', 'ports'], array_keys($payload));
        self::assertSame($payload['runnerId'], $payload['taskQueue']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $payload['secret']);
        self::assertSame(self::REGISTRY_HOST, $payload['registryHost']);
        self::assertSame(
            ['api' => 8082, 'temporal' => 7233, 'registry' => 5000],
            $payload['ports'],
        );
    }

    public function test_no_bootstrap_signature_is_required(): void
    {
        // Регистрация v2 публична: заголовок подписи не нужен и не читается.
        $response = $this->controller()->register(
            $this->request('{"hostname":"runner-1"}'),
            $this->useCase($this->repository()),
        );

        self::assertSame(201, $response->getStatusCode());
    }

    public function test_invalid_json_returns_400(): void
    {
        $response = $this->controller()->register(
            $this->request('{not-json'),
            $this->useCase($this->repository()),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertStringContainsString('runner.registration.invalid_payload', (string) $response->getContent());
    }

    private function controller(): RunnerController
    {
        return new RunnerController();
    }

    private function repository(): InMemoryRunnerRepository
    {
        return new InMemoryRunnerRepository();
    }

    private function useCase(InMemoryRunnerRepository $repository): RegisterRunnerUseCase
    {
        return new RegisterRunnerUseCase(
            $repository,
            new RunnerIdGenerator(),
            RunnerSecretGenerator::fromMasterKey(self::MASTER_KEY),
            self::REGISTRY_HOST,
        );
    }

    private function request(string $body): Request
    {
        return Request::create('/api/runner/register', 'POST', [], [], [], [], $body);
    }
}
