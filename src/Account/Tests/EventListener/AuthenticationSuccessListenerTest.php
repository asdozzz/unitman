<?php

declare(strict_types=1);

namespace App\Account\Tests\EventListener;

use App\Account\Business\Model\Account\RefreshToken;
use App\Account\Business\Model\Account\Role;
use App\Account\Business\Model\JWTUser;
use App\Account\Business\Port\CanGenerateRefreshToken;
use App\Account\Business\Port\RefreshTokenRepository;
use App\Account\Business\UseCase\VipustitRefreshTokenUseCase;
use App\Account\Infra\EventListener\AuthenticationSuccessListener;
use App\Account\Infra\Http\RefreshTokenCookie;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Логин: refresh-токен уходит ТОЛЬКО в HttpOnly cookie, не в тело ответа.
 */
final class AuthenticationSuccessListenerTest extends TestCase
{
    #[Test]
    public function login_sets_refresh_cookie_and_omits_token_from_body(): void
    {
        $sut = $this->listener();
        $response = new Response();

        $event = new AuthenticationSuccessEvent(
            ['token' => 'synthetic-access-token'],
            $this->user(),
            $response,
        );

        $sut($event);

        $data = $event->getData();
        self::assertArrayNotHasKey('refreshToken', $data, 'refresh-токен не должен попадать в JSON');
        self::assertSame('synthetic-access-token', $data['token']);

        $cookies = $response->headers->getCookies();
        self::assertNotEmpty($cookies, 'логин должен выставить refresh-cookie');

        $refreshCookie = null;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === RefreshTokenCookie::NAME) {
                $refreshCookie = $cookie;
            }
        }

        self::assertNotNull($refreshCookie);
        self::assertNotSame('', $refreshCookie->getValue(), 'cookie должна содержать синтетический refresh-токен');
        self::assertTrue($refreshCookie->isHttpOnly());
        self::assertTrue($refreshCookie->isSecure());
        self::assertSame('strict', strtolower((string) $refreshCookie->getSameSite()));
    }

    private function listener(): AuthenticationSuccessListener
    {
        $generator = new class implements CanGenerateRefreshToken {
            public function generate(): string
            {
                return 'synthetic-refresh-value';
            }

            public function hash(string $refreshToken): string
            {
                return hash('sha256', $refreshToken);
            }
        };

        $useCase = new VipustitRefreshTokenUseCase(
            new InMemoryRefreshTokenRepository(),
            $generator,
            3600,
        );

        return new AuthenticationSuccessListener($useCase, new RefreshTokenCookie(3600, true));
    }

    private function user(): JWTUser
    {
        return new JWTUser(
            'synthetic-account-id',
            'synthetic@example.test',
            'synthetic-password-hash',
            [Role::ROLE_USER->value],
        );
    }
}

/**
 * In-memory репозиторий: тест не должен ходить в БД.
 */
final class InMemoryRefreshTokenRepository implements RefreshTokenRepository
{
    /** @var array<string, RefreshToken> */
    private array $tokens = [];

    public function save(RefreshToken $refreshToken): void
    {
        $this->tokens[$refreshToken->getTokenHash()] = $refreshToken;
    }

    public function findByHash(string $tokenHash): ?RefreshToken
    {
        return $this->tokens[$tokenHash] ?? null;
    }

    public function revoke(RefreshToken $refreshToken): void
    {
        $this->tokens[$refreshToken->getTokenHash()] = $refreshToken;
    }

    public function revokeAllByAccountId(string $accountId): void
    {
        foreach ($this->tokens as $hash => $token) {
            if ($token->getAccountId() === $accountId) {
                unset($this->tokens[$hash]);
            }
        }
    }
}
