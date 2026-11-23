<?php

declare(strict_types=1);

namespace App\Account\Tests\UseCase;

use App\Account\Business\Command\SpisokModeleiProvaydera;
use App\Account\Business\Model\OpenCode\OpenCodeCredential;
use App\Account\Business\Port\OpenCodeCredentialRepository;
use App\Account\Business\Port\SecurityService;
use App\Account\Business\Port\UmeetShifrovatSekrety;
use App\Account\Business\UseCase\OpenCode\PoluchitModeliProvayderaUseCase;
use App\Account\Infra\Adapter\HttpZaprositModeliProvaydera;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Загрузка live-списка моделей провайдера с мок-HTTP-клиентом.
 *
 * Настоящий `HttpZaprositModeliProvaydera` оборачивает `MockHttpClient`, поэтому
 * тест проверяет и сам HTTP-адаптер: URL собирается из каталога, ключ уходит
 * только в заголовке, ответы `data[].id`/`models[].name` разбираются.
 */
final class SpisokModeleiTest extends TestCase
{
    private const ACCOUNT_ID = 'acc-spisok-modelei';

    private function useCase(
        MockHttpClient $client,
        ?OpenCodeCredential $credential = null,
    ): PoluchitModeliProvayderaUseCase {
        $securityService = $this->createMock(SecurityService::class);
        $securityService->method('isAdmin')->willReturn(true);
        $securityService->method('getCurrentUserId')->willReturn(self::ACCOUNT_ID);

        $repository = $this->createMock(OpenCodeCredentialRepository::class);

        if ($credential !== null) {
            $repository->method('getById')->willReturn($credential);
        }

        $shifr = $this->createMock(UmeetShifrovatSekrety::class);
        $shifr->method('rasshifrovat')->willReturn('sk-stored-1234');

        return new PoluchitModeliProvayderaUseCase(
            $securityService,
            $repository,
            $shifr,
            new HttpZaprositModeliProvaydera($client),
        );
    }

    #[Test]
    public function live_spisok_razbiraet_data_id_i_ubiraet_dublikaty(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url): MockResponse {
            // Базовый URL берётся ТОЛЬКО из каталога провайдера.
            self::assertSame('GET', $method);
            self::assertSame('https://api.openai.com/v1/models', $url);

            return new MockResponse('{"data":[{"id":"gpt-4o"},{"id":"gpt-4o-mini"},{"id":"gpt-4o"}]}');
        });

        $result = $this->useCase($client)->handle(new SpisokModeleiProvaydera('openai', 'sk-test'));

        $this->assertSame('openai', $result->provider);
        $this->assertSame('live', $result->source);
        $this->assertNull($result->errorKey);
        $this->assertNull($result->providerStatus);
        $this->assertSame(['gpt-4o', 'gpt-4o-mini'], $result->models);
    }

    #[Test]
    public function proksiruet_anthropic_headers_i_models_name(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options = []): MockResponse {
            self::assertSame('https://api.anthropic.com/v1/models', $url);

            $headers = $options['headers'] ?? [];
            $flat = implode(' ', array_map(
                static fn ($header): string => \is_array($header) ? implode(': ', $header) : (string) $header,
                $headers,
            ));
            self::assertStringContainsString('x-api-key', $flat);
            self::assertStringContainsString('anthropic-version', $flat);

            return new MockResponse('{"data":[{"id":"claude-sonnet-4"},{"id":"claude-opus-4"}]}');
        });

        $result = $this->useCase($client)->handle(new SpisokModeleiProvaydera('anthropic', 'sk-ant'));

        $this->assertSame('live', $result->source);
        $this->assertSame(['claude-sonnet-4', 'claude-opus-4'], $result->models);
    }

    #[Test]
    public function ollama_perevodit_formu_v_ruchnoy_rezhim_bez_http(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            throw new \LogicException('live-запрос не должен выполняться');
        });

        $result = $this->useCase($client)->handle(new SpisokModeleiProvaydera('ollama', 'ignored'));

        $this->assertSame('manual', $result->source);
        $this->assertNull($result->errorKey);
        $this->assertNull($result->providerStatus);
        $this->assertSame([], $result->models);
    }

    #[Test]
    public function oshibka_provajdera_daet_manual_i_error_key(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse(
            '{"error":"unauthorized"}',
            ['http_code' => 401],
        ));

        $result = $this->useCase($client)->handle(new SpisokModeleiProvaydera('openai', 'sk-bad'));

        $this->assertSame('manual', $result->source);
        $this->assertSame('account.opencode_credential.provider_error', $result->errorKey);
        $this->assertSame(401, $result->providerStatus, 'невалидный ключ отличим от гео-блокировки');
        $this->assertSame([], $result->models, 'форма переходит на ручной ввод');
    }

    #[Test]
    public function setevaya_oshibka_daet_status_null(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            throw new \Symfony\Component\HttpClient\Exception\TransportException('dns failure');
        });

        $result = $this->useCase($client)->handle(new SpisokModeleiProvaydera('openai', 'sk-test'));

        $this->assertSame('manual', $result->source);
        $this->assertSame('account.opencode_credential.provider_unavailable', $result->errorKey);
        $this->assertNull($result->providerStatus, 'ответа провайдера не было');
        $this->assertSame([], $result->models);
    }

    #[Test]
    public function neizvestnyi_provajder_daet_oshibku_v_otvete(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse('{}'));

        $result = $this->useCase($client)->handle(new SpisokModeleiProvaydera('nope'));

        $this->assertSame('manual', $result->source);
        $this->assertSame('account.opencode_credential.unknown_provider', $result->errorKey);
        $this->assertNull($result->providerStatus);
        $this->assertSame([], $result->models);
    }

    #[Test]
    public function klyuch_beretsya_iz_sokhranennyh_rekvizitov_bez_apikey(): void
    {
        $credential = OpenCodeCredential::create(
            'cred-stored',
            self::ACCOUNT_ID,
            'stored',
            'openai',
            'https://api.openai.com/v1',
            ['gpt-4o'],
            'gpt-4o',
            'cipher',
            '****1234',
            1000,
        );

        $client = new MockHttpClient(static function (string $method, string $url, array $options = []): MockResponse {
            $headers = $options['headers'] ?? [];
            $flat = implode(' ', array_map(
                static fn ($header): string => \is_array($header) ? implode(': ', $header) : (string) $header,
                $headers,
            ));
            self::assertStringContainsString('Bearer sk-stored-1234', $flat);

            return new MockResponse('{"data":[{"id":"gpt-4o"},{"id":"o3"}]}');
        });

        $result = $this->useCase($client, $credential)->handle(new SpisokModeleiProvaydera(
            provider: 'openai',
            apiKey: null,
            credentialId: 'cred-stored',
        ));

        $this->assertSame('live', $result->source);
        $this->assertSame(['gpt-4o', 'o3'], $result->models);
    }
}
