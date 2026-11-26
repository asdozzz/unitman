<?php

declare(strict_types=1);

namespace App\Runner\Tests\Business\UseCase;

use App\Runner\Business\UseCase\RegisterRunner\RunnerRegistrationInvalidPayloadException;
use App\Runner\Business\UseCase\RegisterRunnerUseCase;
use App\Runner\Business\Utils\RunnerIdGenerator;
use App\Runner\Business\Utils\RunnerSecretGenerator;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use PHPUnit\Framework\TestCase;

final class RegisterRunnerUseCaseTest extends TestCase
{
    private const MASTER_KEY = 'runner-master-key';
    private const REGISTRY_HOST = 'registry.example.com';

    public function test_valid_registration_returns_exact_v2_contract(): void
    {
        $repository = new InMemoryRunnerRepository();
        $body = json_encode([
            'hostname' => 'runner-1.example.com',
            'version' => '1.4.2',
        ], JSON_THROW_ON_ERROR);

        $result = $this->useCase($repository)->handle($body);

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $result->runnerId,
        );
        self::assertSame($result->runnerId, $result->taskQueue, 'taskQueue обязан быть равен runnerId');
        self::assertSame(self::REGISTRY_HOST, $result->registryHost);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result->secret);
        self::assertSame(
            hash_hmac('sha256', $result->runnerId, self::MASTER_KEY),
            $result->secret,
            'secret должен быть детерминированным HMAC(runnerId, masterKey)',
        );
        self::assertSame(
            ['api' => 8082, 'temporal' => 7233, 'registry' => 5000],
            $result->ports,
        );
    }

    public function test_ports_come_from_config_when_provided(): void
    {
        $useCase = new RegisterRunnerUseCase(
            new InMemoryRunnerRepository(),
            new RunnerIdGenerator(),
            RunnerSecretGenerator::fromMasterKey(self::MASTER_KEY),
            self::REGISTRY_HOST,
            '18082',
            '17233',
            '15000',
        );

        $result = $useCase->handle('{}');

        self::assertSame(
            ['api' => 18082, 'temporal' => 17233, 'registry' => 15000],
            $result->ports,
        );
    }

    public function test_registration_persists_state_without_secret(): void
    {
        $repository = new InMemoryRunnerRepository();
        $body = json_encode([
            'hostname' => 'runner-1.example.com',
            'version' => '1.4.2',
        ], JSON_THROW_ON_ERROR);

        $result = $this->useCase($repository)->handle($body);

        $state = $repository->findById($result->runnerId);
        self::assertNotNull($state);
        self::assertTrue($state->isActive());
        self::assertSame($result->runnerId, $state->getTaskQueue());
        self::assertSame('runner-1.example.com', $state->getHostname());
        self::assertSame(self::REGISTRY_HOST, $state->getRegistryHost());
        self::assertNotNull($state->getRegisteredAt());

        $serialized = $state->jsonSerialize();
        self::assertArrayNotHasKey('secret', $serialized);
        self::assertStringNotContainsString($result->secret, json_encode($serialized, JSON_THROW_ON_ERROR));
    }

    public function test_registration_ignores_unknown_extra_fields(): void
    {
        // Контракт v2 принимает только {hostname, version}; прочие ключи
        // не читаются и не сохраняются.
        $repository = new InMemoryRunnerRepository();
        $result = $this->useCase($repository)->handle(
            '{"hostname":"r1","unexpectedField":"ignored"}'
        );

        self::assertSame($result->runnerId, $result->taskQueue);

        $state = $repository->findById($result->runnerId);
        self::assertNotNull($state);
        self::assertNull($state->getPublicAddress());
    }

    public function test_missing_master_key_fails_closed_even_when_app_secret_is_set(): void
    {
        // WS-9: APP_SECRET больше не является fallback-ключом раннера.
        $keys = new \App\Secrets\Infra\Adapter\SecretEncryptionKeyProvider(
            new \App\Secrets\Infra\Adapter\ArraySecretProvider([
                'APP_SECRET' => 'test-app-secret-not-for-production',
            ]),
        );
        $generator = new RunnerSecretGenerator($keys);
        $repository = new InMemoryRunnerRepository();

        $useCase = new RegisterRunnerUseCase(
            $repository,
            new RunnerIdGenerator(),
            $generator,
            self::REGISTRY_HOST,
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('runner_secret.master_key_not_configured');

        $useCase->handle('{"hostname":"r1"}');
    }

    public function test_master_key_derivation_is_identical_to_legacy_hmac(): void
    {
        $generator = RunnerSecretGenerator::fromMasterKey(self::MASTER_KEY);

        self::assertSame(
            hash_hmac('sha256', 'runner-1', self::MASTER_KEY),
            $generator->generate('runner-1'),
        );
    }

    public function test_invalid_json_is_rejected(): void
    {
        $this->expectException(RunnerRegistrationInvalidPayloadException::class);
        $this->expectExceptionMessage('runner.registration.invalid_payload');

        $this->useCase(new InMemoryRunnerRepository())->handle('{not-json');
    }

    public function test_non_object_json_is_rejected(): void
    {
        $this->expectException(RunnerRegistrationInvalidPayloadException::class);

        $this->useCase(new InMemoryRunnerRepository())->handle('"just a string"');
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
}
