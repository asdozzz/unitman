<?php

declare(strict_types=1);

namespace App\Runner\Tests\Api;

use App\Account\Business\Model\JWTUser;
use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Utils\RunnerSecretGenerator;
use App\Runner\Infra\Repository\SqlRunnerStateRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * HTTP-level contract проверки контракта раннера (v2).
 *
 * В отличие от контроллерных тестов эти кейсы проходят через реальный kernel
 * и `BaseExceptionListener`, поэтому проверяют именно HTTP-статусы, которые
 * видит раннер: 200/204/400/401/403/404.
 */
final class RunnerHttpContractTest extends WebTestCase
{
    private const TASK_QUEUE = 'unitman-runner-queue';
    private const PROJECT_ID = 'project-http-contract';

    private KernelBrowser $client;
    private Connection $connection;
    private RunnerSecretGenerator $secretGenerator;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Один kernel на тест: транзакция откатывается в tearDown, а запросы
        // не пересоздают контейнер (иначе соединение БД теряет транзакцию).
        $this->client->disableReboot();

        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);
        $this->connection->beginTransaction();

        // Секрет вычисляется тем же алгоритмом, что и в DI: только
        // RUNNER_SECRET_MASTER_KEY. Fallback на APP_SECRET удалён в WS-9,
        // поэтому тесты обязаны задавать master key синтетически.
        $masterKey = $this->env('RUNNER_SECRET_MASTER_KEY');

        if ($masterKey === null || trim($masterKey) === '') {
            $masterKey = 'test-runner-master-key-not-for-production';
        }

        $this->secretGenerator = RunnerSecretGenerator::fromMasterKey($masterKey);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection) && $this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function test_health_valid_signature_returns_200(): void
    {
        $runnerId = 'http-health-ok';
        $this->saveRunner($runnerId);

        $body = $this->healthBody($runnerId);
        $this->postHealth($runnerId, $body, $this->sign($runnerId, $body));

        self::assertSame(200, $this->responseStatus());
        self::assertSame('true', $this->client->getResponse()->getContent());
    }

    public function test_health_tampered_signature_returns_401(): void
    {
        $runnerId = 'http-health-tampered';
        $this->saveRunner($runnerId);

        $this->postHealth($runnerId, $this->healthBody($runnerId), 'deadbeef');

        self::assertSame(401, $this->responseStatus());
    }

    public function test_health_unknown_runner_returns_404(): void
    {
        $runnerId = 'http-health-unknown';

        $this->postHealth($runnerId, $this->healthBody($runnerId), 'deadbeef');

        self::assertSame(404, $this->responseStatus());
    }

    public function test_save_steps_tampered_signature_returns_401(): void
    {
        $runnerId = 'http-savesteps-tampered';
        $this->saveRunner($runnerId);

        $body = json_encode(
            ['responseId' => 'progress-token', 'stepsContent' => '[]'],
            JSON_THROW_ON_ERROR,
        );

        $this->client->request(
            'POST',
            '/api/runner/saveSteps',
            [],
            [],
            $this->callbackHeaders($runnerId, 'deadbeef'),
            $body,
        );

        self::assertSame(401, $this->responseStatus());
    }

    public function test_build_state_get_tampered_signature_returns_401(): void
    {
        $runnerId = 'http-buildstate-get';
        $this->saveRunner($runnerId);

        $this->client->request(
            'GET',
            sprintf('/api/runner/%s/projects/%s/build-state', $runnerId, self::PROJECT_ID),
            [],
            [],
            $this->callbackHeaders($runnerId, 'deadbeef'),
        );

        self::assertSame(401, $this->responseStatus());
    }

    public function test_build_state_put_tampered_signature_returns_401(): void
    {
        $runnerId = 'http-buildstate-put';
        $this->saveRunner($runnerId);

        $this->client->request(
            'PUT',
            sprintf('/api/runner/%s/projects/%s/build-state', $runnerId, self::PROJECT_ID),
            [],
            [],
            $this->callbackHeaders($runnerId, 'deadbeef'),
            json_encode(['builtAt' => 1764000000], JSON_THROW_ON_ERROR),
        );

        self::assertSame(401, $this->responseStatus());
    }

    public function test_public_address_invalid_returns_400(): void
    {
        $runnerId = 'http-public-address-invalid';
        $this->saveRunner($runnerId);
        $this->loginAsAdmin();

        foreach (['ftp://x', 'http://user:pass@host'] as $invalidAddress) {
            $this->putPublicAddress($runnerId, $invalidAddress);

            self::assertSame(400, $this->responseStatus(), $invalidAddress);
        }
    }

    public function test_public_address_unknown_runner_returns_404(): void
    {
        $this->loginAsAdmin();

        $this->putPublicAddress('http-public-address-unknown', 'https://runner.example.com');

        self::assertSame(404, $this->responseStatus());
    }

    public function test_public_address_non_admin_returns_403(): void
    {
        $runnerId = 'http-public-address-forbidden';
        $this->saveRunner($runnerId);

        $this->client->loginUser($this->user(['ROLE_USER']));
        $this->putPublicAddress($runnerId, 'https://runner.example.com');

        self::assertSame(403, $this->responseStatus());
    }

    public function test_public_address_valid_returns_200_and_delete_returns_204(): void
    {
        $runnerId = 'http-public-address-ok';
        $this->saveRunner($runnerId);

        $this->loginAsAdmin();
        $this->putPublicAddress($runnerId, 'https://runner.example.com');

        self::assertSame(200, $this->responseStatus());
        self::assertSame(
            ['runnerId' => $runnerId, 'publicAddress' => 'https://runner.example.com'],
            json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR),
        );

        $this->client->request('DELETE', sprintf('/api/runner/%s/public-address', $runnerId));

        self::assertSame(204, $this->responseStatus());
    }

    private function saveRunner(string $runnerId): void
    {
        /** @var SqlRunnerStateRepository $repository */
        $repository = static::getContainer()->get(SqlRunnerStateRepository::class);
        $repository->save(new RunnerState($runnerId, self::TASK_QUEUE, true));
    }

    private function healthBody(string $runnerId): string
    {
        return json_encode([
            'runnerId' => $runnerId,
            'taskQueue' => self::TASK_QUEUE,
            'active' => true,
            'unixtime' => 1764000000,
        ], JSON_THROW_ON_ERROR);
    }

    private function sign(string $runnerId, string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secretGenerator->generate($runnerId));
    }

    /**
     * @return array<string, string>
     */
    private function callbackHeaders(string $runnerId, string $signature): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RUNNER_ID' => $runnerId,
            'HTTP_X_RUNNER_SIGNATURE' => $signature,
        ];
    }

    private function postHealth(string $runnerId, string $body, string $signature): void
    {
        $this->client->request(
            'POST',
            '/api/runner/health',
            [],
            [],
            $this->callbackHeaders($runnerId, $signature),
            $body,
        );
    }

    private function putPublicAddress(string $runnerId, string $publicAddress): void
    {
        $this->client->request(
            'PUT',
            sprintf('/api/runner/%s/public-address', $runnerId),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['publicAddress' => $publicAddress], JSON_THROW_ON_ERROR),
        );
    }

    private function loginAsAdmin(): void
    {
        $this->client->loginUser($this->user(['ROLE_USER', 'ROLE_ADMIN']));
    }

    /**
     * @param string[] $roles
     */
    private function user(array $roles): JWTUser
    {
        return new JWTUser('http-contract-user', 'http-contract@example.com', 'not-used', $roles);
    }

    private function responseStatus(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }

    private function env(string $name): ?string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        return is_string($value) ? $value : null;
    }
}
