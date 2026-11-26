<?php

declare(strict_types=1);

namespace App\Account\Tests\Api;

use App\Account\Business\Command\RegisterAccount;
use App\Account\Business\Model\Account\Role;
use App\Account\Business\Port\SecurityService;
use App\Account\Business\UseCase\RegisterAccountUseCase;
use App\Account\Business\UseCase\VipustitRefreshTokenUseCase;
use App\Account\Infra\Http\RefreshTokenCookie;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\BrowserKit\Cookie as BrowserKitCookie;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Cookie as HttpCookie;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP-контракт refresh/logout через HttpOnly cookie.
 *
 * CORS_ALLOW_ORIGIN в тестовом окружении задаётся в .env.test.
 */
final class RefreshCookieFlowTest extends WebTestCase
{
    private const ORIGIN = 'http://localhost:9000';
    private const CSRF_HEADER = ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];

    private KernelBrowser $client;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->connection = static::getContainer()->get(Connection::class);
        $this->connection->beginTransaction();

        $securityService = $this->createMock(SecurityService::class);
        $securityService->method('isAdmin')->willReturn(true);
        static::getContainer()->set(SecurityService::class, $securityService);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection) && $this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    #[Test]
    public function refresh_without_cookie_returns_401(): void
    {
        $this->postRefresh();

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->responseStatus());
    }

    #[Test]
    public function refresh_with_foreign_origin_returns_403(): void
    {
        $this->withRefreshCookie($this->issueRefreshToken());

        $this->client->request('POST', '/api/account/refreshToken', [], [], [
            'HTTP_ORIGIN' => 'https://evil.example.test',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->responseStatus());
    }

    #[Test]
    public function refresh_without_custom_header_returns_403(): void
    {
        $this->withRefreshCookie($this->issueRefreshToken());

        $this->client->request('POST', '/api/account/refreshToken', [], [], [
            'HTTP_ORIGIN' => self::ORIGIN,
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->responseStatus());
    }

    #[Test]
    public function refresh_from_cookie_rotates_cookie_and_returns_only_access_token(): void
    {
        $value = $this->issueRefreshToken();
        $this->withRefreshCookie($value);

        $this->postRefresh();

        self::assertSame(Response::HTTP_OK, $this->responseStatus());

        $body = $this->jsonBody();
        self::assertArrayHasKey('token', $body['data'] ?? []);
        self::assertArrayNotHasKey('refreshToken', $body['data'] ?? [], 'refresh-токен не должен возвращаться в теле');

        $cookie = $this->refreshCookieFromResponse();
        self::assertNotNull($cookie);
        self::assertNotSame($value, $cookie->getValue(), 'refresh-токен должен ротироваться');
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('strict', strtolower((string) $cookie->getSameSite()));
        self::assertSame('/api/account', $cookie->getPath());
    }

    #[Test]
    public function logout_clears_refresh_cookie(): void
    {
        $this->withRefreshCookie($this->issueRefreshToken());

        $this->client->request('POST', '/api/account/logout', [], [], [
            'HTTP_ORIGIN' => self::ORIGIN,
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        self::assertSame(Response::HTTP_OK, $this->responseStatus());

        $cookie = $this->refreshCookieFromResponse();
        self::assertNotNull($cookie, 'logout должен выставить очищающую cookie');
        self::assertSame('', $cookie->getValue());
        self::assertLessThan(time(), $cookie->getExpiresTime());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('/api/account', $cookie->getPath());
    }

    #[Test]
    public function preflight_from_allowed_origin_echoes_exact_origin_with_credentials(): void
    {
        $this->client->request('OPTIONS', '/api/account/logout', [], [], [
            'HTTP_ORIGIN' => self::ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-requested-with',
        ]);

        $response = $this->client->getResponse();
        self::assertSame(self::ORIGIN, $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function preflight_from_disallowed_origin_has_no_acao(): void
    {
        $this->client->request('OPTIONS', '/api/account/logout', [], [], [
            'HTTP_ORIGIN' => 'https://evil.example.test',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $response = $this->client->getResponse();
        self::assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    private function postRefresh(): void
    {
        $this->client->request('POST', '/api/account/refreshToken', [], [], [
            'HTTP_ORIGIN' => self::ORIGIN,
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);
    }

    private function issueRefreshToken(): string
    {
        $accountId = static::getContainer()->get(RegisterAccountUseCase::class)->handle(
            new RegisterAccount('ws6_refresh_' . bin2hex(random_bytes(4)) . '@example.test', Role::ROLE_USER->value, 'ru'),
        );

        return static::getContainer()->get(VipustitRefreshTokenUseCase::class)->handle($accountId);
    }

    private function withRefreshCookie(string $value): void
    {
        $this->client->getCookieJar()->set(
            new BrowserKitCookie(RefreshTokenCookie::NAME, $value, null, '/api/account', 'localhost'),
        );
    }

    private function refreshCookieFromResponse(): ?HttpCookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === RefreshTokenCookie::NAME) {
                return $cookie;
            }
        }

        return null;
    }

    private function responseStatus(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }

    private function jsonBody(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
