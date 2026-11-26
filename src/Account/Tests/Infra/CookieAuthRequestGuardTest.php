<?php

declare(strict_types=1);

namespace App\Account\Tests\Infra;

use App\Account\Infra\Http\CookieAuthRequestGuard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Fail-closed CSRF-защита cookie-эндпоинтов.
 */
final class CookieAuthRequestGuardTest extends TestCase
{
    private const ORIGIN = 'https://app.example.test';

    #[Test]
    public function allowed_origin_and_custom_header_pass(): void
    {
        $sut = new CookieAuthRequestGuard([self::ORIGIN]);

        $sut->assertAllowed($this->request([
            'HTTP_ORIGIN' => self::ORIGIN,
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]));

        self::assertTrue(true, 'guard не должен бросать исключение');
    }

    #[Test]
    public function missing_custom_header_is_denied(): void
    {
        $sut = new CookieAuthRequestGuard([self::ORIGIN]);

        $this->expectException(AccessDeniedHttpException::class);

        $sut->assertAllowed($this->request([
            'HTTP_ORIGIN' => self::ORIGIN,
        ]));
    }

    #[Test]
    public function foreign_origin_is_denied(): void
    {
        $sut = new CookieAuthRequestGuard([self::ORIGIN]);

        $this->expectException(AccessDeniedHttpException::class);

        $sut->assertAllowed($this->request([
            'HTTP_ORIGIN' => 'https://evil.example.test',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]));
    }

    #[Test]
    public function missing_origin_is_denied_even_with_header(): void
    {
        $sut = new CookieAuthRequestGuard([self::ORIGIN]);

        $this->expectException(AccessDeniedHttpException::class);

        $sut->assertAllowed($this->request([
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]));
    }

    #[Test]
    public function referer_fallback_is_checked_against_allowlist(): void
    {
        $sut = new CookieAuthRequestGuard([self::ORIGIN]);

        $sut->assertAllowed($this->request([
            'HTTP_REFERER' => self::ORIGIN . '/unit/list',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]));

        self::assertTrue(true);
    }

    #[Test]
    public function empty_allowlist_denies_everything(): void
    {
        $sut = new CookieAuthRequestGuard([]);

        $this->expectException(AccessDeniedHttpException::class);

        $sut->assertAllowed($this->request([
            'HTTP_ORIGIN' => self::ORIGIN,
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]));
    }

    private function request(array $server): Request
    {
        return Request::create('/api/account/refreshToken', 'POST', server: $server);
    }
}
