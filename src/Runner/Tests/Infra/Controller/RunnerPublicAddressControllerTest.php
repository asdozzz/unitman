<?php

declare(strict_types=1);

namespace App\Runner\Tests\Infra\Controller;

use App\Runner\Business\Model\RunnerState;
use App\Runner\Business\Port\UmeetProveryatPravaAdmina;
use App\Runner\Business\UseCase\PublicAddress\ProveritPublicAddressUseCase;
use App\Runner\Business\UseCase\PublicAddress\UdalitPublicAddressUseCase;
use App\Runner\Business\UseCase\PublicAddress\UstanovitPublicAddressUseCase;
use App\Runner\Business\Utils\RunnerPublicAddressValidator;
use App\Runner\Infra\Controller\RunnerPublicAddressController;
use App\Runner\Tests\Support\InMemoryRunnerRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class RunnerPublicAddressControllerTest extends TestCase
{
    private const RUNNER_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const PUBLIC_ADDRESS = 'https://runner-1.example.com';

    public function test_put_persists_address_and_returns_contract_payload(): void
    {
        $repository = $this->repository();

        $response = $this->controller()->putPublicAddress(
            self::RUNNER_ID,
            $this->request(['publicAddress' => self::PUBLIC_ADDRESS]),
            $this->ustanovitUseCase($repository),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['runnerId' => self::RUNNER_ID, 'publicAddress' => self::PUBLIC_ADDRESS],
            $this->json($response->getContent()),
        );
        self::assertSame(self::PUBLIC_ADDRESS, $repository->getById(self::RUNNER_ID)->getPublicAddress());
    }

    public function test_put_unknown_runner_returns_404(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('runner.not_found');

        $this->controller()->putPublicAddress(
            'unknown-runner',
            $this->request(['publicAddress' => self::PUBLIC_ADDRESS]),
            $this->ustanovitUseCase($this->repository()),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAddresses(): iterable
    {
        yield 'relative' => ['runner-1.example.com'];
        yield 'ftp scheme' => ['ftp://runner-1.example.com'];
        yield 'empty host' => ['https://'];
        yield 'userinfo' => ['https://user:pass@runner-1.example.com'];
    }

    #[DataProvider('invalidAddresses')]
    public function test_put_rejects_invalid_address(string $address): void
    {
        $repository = $this->repository();

        try {
            $this->controller()->putPublicAddress(
                self::RUNNER_ID,
                $this->request(['publicAddress' => $address]),
                $this->ustanovitUseCase($repository),
            );
            self::fail('Ожидалось BadRequestHttpException');
        } catch (BadRequestHttpException $exception) {
            self::assertSame(RunnerPublicAddressValidator::INVALID_CODE, $exception->getMessage());
        }

        self::assertNull($repository->getById(self::RUNNER_ID)->getPublicAddress());
    }

    public function test_put_rejects_missing_or_non_string_address(): void
    {
        foreach ([['publicAddress' => null], ['publicAddress' => 123], []] as $payload) {
            try {
                $this->controller()->putPublicAddress(
                    self::RUNNER_ID,
                    $this->request($payload),
                    $this->ustanovitUseCase($this->repository()),
                );
                self::fail('Ожидалось BadRequestHttpException');
            } catch (BadRequestHttpException $exception) {
                self::assertSame(RunnerPublicAddressValidator::INVALID_CODE, $exception->getMessage());
            }
        }
    }

    public function test_delete_clears_address_and_returns_204(): void
    {
        $repository = $this->repository();
        $repository->savePublicAddress(self::RUNNER_ID, self::PUBLIC_ADDRESS);

        $response = $this->controller()->deletePublicAddress(
            self::RUNNER_ID,
            $this->udalitUseCase($repository),
        );

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getContent());
        self::assertNull($repository->getById(self::RUNNER_ID)->getPublicAddress());
    }

    public function test_delete_unknown_runner_returns_404(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller()->deletePublicAddress('unknown-runner', $this->udalitUseCase($this->repository()));
    }

    public function test_check_reports_reachable_without_persisting(): void
    {
        $repository = $this->repository();
        $capturedUrl = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl): MockResponse {
            $capturedUrl = $url;
            self::assertSame('GET', $method);

            return new MockResponse('{"status":"ok"}', ['http_code' => 200]);
        });

        $response = $this->controller()->checkPublicAddress(
            self::RUNNER_ID,
            $this->request(['publicAddress' => self::PUBLIC_ADDRESS]),
            $this->proveritUseCase($repository, $httpClient),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['reachable' => true], $this->json($response->getContent()));
        // Проверка идёт на служебный health-эндпоинт nginx-прокси, а не на
        // корень публичного адреса.
        self::assertSame(self::PUBLIC_ADDRESS . '/__unitman/health', $capturedUrl);
        self::assertNull(
            $repository->getById(self::RUNNER_ID)->getPublicAddress(),
            'проверка не должна сохранять адрес',
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function localProxyAddresses(): iterable
    {
        // Symfony HttpClient нормализует порт 80 для http: в транспорте он
        // опускается, но запрос всё равно уходит на контейнерный порт nginx.
        yield 'localhost' => ['http://localhost:8087', 'http://uproxy/__unitman/health'];
        yield 'ipv4 loopback' => ['http://127.0.0.1:8087', 'http://uproxy/__unitman/health'];
        yield 'localhost with path and query' => [
            'http://localhost:8087/foo?bar=1',
            'http://uproxy/foo/__unitman/health',
        ];
    }

    #[DataProvider('localProxyAddresses')]
    public function test_check_rewrites_local_proxy_address_to_internal_name(
        string $localAddress,
        string $expectedUrl,
    ): void {
        $capturedUrl = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl): MockResponse {
            $capturedUrl = $url;

            return new MockResponse("Unitman Proxy Host\n", ['http_code' => 200]);
        });

        $response = $this->controller()->checkPublicAddress(
            self::RUNNER_ID,
            $this->request(['publicAddress' => $localAddress]),
            $this->proveritUseCase($this->repository(), $httpClient),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['reachable' => true], $this->json($response->getContent()));
        // localhost внутри контейнера бэкенда — это сам контейнер, поэтому
        // проверка идёт по внутреннему имени прокси и контейнерному порту 80.
        self::assertSame($expectedUrl, $capturedUrl);
    }

    public function test_check_normalizes_trailing_slash_before_health_path(): void
    {
        $capturedUrl = null;

        $httpClient = new MockHttpClient(static function (string $method, string $url) use (&$capturedUrl): MockResponse {
            $capturedUrl = $url;

            return new MockResponse('', ['http_code' => 200]);
        });

        $this->controller()->checkPublicAddress(
            self::RUNNER_ID,
            $this->request(['publicAddress' => self::PUBLIC_ADDRESS . '/']),
            $this->proveritUseCase($this->repository(), $httpClient),
        );

        self::assertSame(self::PUBLIC_ADDRESS . '/__unitman/health', $capturedUrl);
    }

    public function test_check_returns_unreachable_for_http_error_without_throwing(): void
    {
        $response = $this->controller()->checkPublicAddress(
            self::RUNNER_ID,
            $this->request(['publicAddress' => self::PUBLIC_ADDRESS]),
            $this->proveritUseCase(
                $this->repository(),
                new MockHttpClient(new MockResponse('', ['http_code' => 503])),
            ),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['reachable' => false, 'error' => 'http_status_503'],
            $this->json($response->getContent()),
        );
    }

    public function test_check_returns_unreachable_for_transport_error_without_throwing(): void
    {
        $httpClient = new MockHttpClient(static function (): MockResponse {
            throw new TransportException('dns failure');
        });

        $response = $this->controller()->checkPublicAddress(
            self::RUNNER_ID,
            $this->request(['publicAddress' => self::PUBLIC_ADDRESS]),
            $this->proveritUseCase($this->repository(), $httpClient),
        );

        self::assertSame(200, $response->getStatusCode());

        /** @var array{reachable: bool, error: string} $payload */
        $payload = $this->json($response->getContent());
        self::assertFalse($payload['reachable']);
        self::assertSame('dns failure', $payload['error']);
    }

    public function test_check_unknown_runner_returns_404(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller()->checkPublicAddress(
            'unknown-runner',
            $this->request(['publicAddress' => self::PUBLIC_ADDRESS]),
            $this->proveritUseCase($this->repository(), new MockHttpClient()),
        );
    }

    public function test_non_admin_gets_403_for_all_three_operations(): void
    {
        $repository = $this->repository();
        $security = $this->security(false);

        $operations = [
            fn (RunnerPublicAddressController $controller) => $controller->putPublicAddress(
                self::RUNNER_ID,
                $this->request(['publicAddress' => self::PUBLIC_ADDRESS]),
                new UstanovitPublicAddressUseCase($repository, $security, new RunnerPublicAddressValidator()),
            ),
            fn (RunnerPublicAddressController $controller) => $controller->deletePublicAddress(
                self::RUNNER_ID,
                new UdalitPublicAddressUseCase($repository, $security),
            ),
            fn (RunnerPublicAddressController $controller) => $controller->checkPublicAddress(
                self::RUNNER_ID,
                $this->request(['publicAddress' => self::PUBLIC_ADDRESS]),
                new ProveritPublicAddressUseCase(
                    $repository,
                    $security,
                    new RunnerPublicAddressValidator(),
                    new MockHttpClient(),
                ),
            ),
        ];

        foreach ($operations as $operation) {
            try {
                $operation($this->controller());
                self::fail('Ожидалось AccessDeniedHttpException');
            } catch (AccessDeniedHttpException $exception) {
                self::assertSame(403, $exception->getStatusCode());
                self::assertSame('security.access_denied', $exception->getMessage());
            }
        }
    }

    private function controller(): RunnerPublicAddressController
    {
        return new RunnerPublicAddressController();
    }

    private function repository(): InMemoryRunnerRepository
    {
        $repository = new InMemoryRunnerRepository();
        $repository->save(new RunnerState(self::RUNNER_ID, self::RUNNER_ID, true));

        return $repository;
    }

    private function security(bool $isAdmin): UmeetProveryatPravaAdmina
    {
        $security = $this->createMock(UmeetProveryatPravaAdmina::class);
        $security->method('estAdministrator')->willReturn($isAdmin);

        return $security;
    }

    private function ustanovitUseCase(InMemoryRunnerRepository $repository): UstanovitPublicAddressUseCase
    {
        return new UstanovitPublicAddressUseCase(
            $repository,
            $this->security(true),
            new RunnerPublicAddressValidator(),
        );
    }

    private function udalitUseCase(InMemoryRunnerRepository $repository): UdalitPublicAddressUseCase
    {
        return new UdalitPublicAddressUseCase($repository, $this->security(true));
    }

    private function proveritUseCase(
        InMemoryRunnerRepository $repository,
        MockHttpClient $httpClient,
    ): ProveritPublicAddressUseCase {
        return new ProveritPublicAddressUseCase(
            $repository,
            $this->security(true),
            new RunnerPublicAddressValidator(),
            $httpClient,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload): Request
    {
        return Request::create(
            '/api/runner/' . self::RUNNER_ID . '/public-address',
            'PUT',
            [],
            [],
            [],
            [],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function json(string $content): array
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }
}
