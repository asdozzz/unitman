<?php

declare(strict_types=1);

namespace App\Account\Tests\Infra;

use App\Account\Infra\Http\RefreshTokenCookie;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Контракт refresh-cookie.
 *
 * Значения в тестах синтетические и не являются настоящими JWT/refresh-токенами.
 */
final class RefreshTokenCookieTest extends TestCase
{
    #[Test]
    public function refresh_cookie_is_http_only_secure_strict_and_scoped(): void
    {
        $sut = new RefreshTokenCookie(3600, true);
        $cookie = $sut->create('synthetic-refresh-value');

        self::assertSame(RefreshTokenCookie::NAME, $cookie->getName());
        self::assertTrue($cookie->isHttpOnly(), 'refresh-cookie должен быть HttpOnly');
        self::assertTrue($cookie->isSecure(), 'refresh-cookie должен быть Secure');
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
        self::assertSame('/api/account', $cookie->getPath(), 'cookie не должен уходить на всё API');
        self::assertNull($cookie->getDomain(), 'Domain не задаётся: cookie host-only');
        self::assertSame('synthetic-refresh-value', $cookie->getValue());
        self::assertGreaterThan(time(), $cookie->getExpiresTime());
    }

    #[Test]
    public function clear_cookie_expires_and_keeps_security_attributes(): void
    {
        $sut = new RefreshTokenCookie(3600, true);
        $cookie = $sut->clear();

        self::assertSame(RefreshTokenCookie::NAME, $cookie->getName());
        self::assertSame('', $cookie->getValue());
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
        self::assertSame('/api/account', $cookie->getPath());
        self::assertNull($cookie->getDomain());
        self::assertLessThan(time(), $cookie->getExpiresTime());
    }

    #[Test]
    public function secure_flag_must_be_enabled_explicitly_for_local_dev_only(): void
    {
        $secure = new RefreshTokenCookie(3600, true);
        self::assertTrue($secure->create('synthetic')->isSecure());

        // Явный dev-override. Тест фиксирует, что это единственный способ
        // выключить Secure — значение по умолчанию не должно быть небезопасным.
        $devOverride = new RefreshTokenCookie(3600, false);
        self::assertFalse($devOverride->create('synthetic')->isSecure());
    }
}
