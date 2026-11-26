<?php

declare(strict_types=1);

namespace App\Account\Infra\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Единственное место, где формируется refresh-cookie.
 *
 * Контракт:
 *  - имя: {@see self::NAME};
 *  - HttpOnly: JavaScript не может прочитать значение;
 *  - Secure: включён по умолчанию, выключается только явным dev-override
 *    (`COOKIE_SECURE=false`, пишется в лог по имени переменной);
 *  - SameSite=Strict: cookie не отправляется при cross-site переходах;
 *  - Path=/api/account: cookie не уходит на остальные API-эндпоинты;
 *  - Domain не задаётся (host-only);
 *  - Max-Age совпадает с TTL refresh-токена.
 */
final class RefreshTokenCookie
{
    public const NAME = 'unitman_refresh_token';
    public const PATH = '/api/account';

    public function __construct(
        private readonly int $refreshTokenTtl,
        private readonly bool $refreshTokenCookieSecure = true,
        private readonly ?LoggerInterface $logger = null,
    ) {
        if (!$this->refreshTokenCookieSecure) {
            // Явный dev-override. Значение/токен не логируются — только имя
            // переменной, чтобы факт небезопасной конфигурации был заметен.
            $this->logger?->warning(
                'Refresh cookie Secure disabled by explicit configuration; do not use in production.',
                ['env_var' => 'COOKIE_SECURE'],
            );
        }
    }

    public function create(string $token): Cookie
    {
        return new Cookie(
            self::NAME,
            $token,
            time() + $this->refreshTokenTtl,
            self::PATH,
            null,
            $this->refreshTokenCookieSecure,
            true,
            false,
            Cookie::SAMESITE_STRICT,
        );
    }

    public function clear(): Cookie
    {
        return new Cookie(
            self::NAME,
            '',
            1,
            self::PATH,
            null,
            $this->refreshTokenCookieSecure,
            true,
            false,
            Cookie::SAMESITE_STRICT,
        );
    }
}
