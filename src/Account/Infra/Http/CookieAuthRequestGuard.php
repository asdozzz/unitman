<?php

declare(strict_types=1);

namespace App\Account\Infra\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Fail-closed CSRF-защита cookie-эндпоинтов (`refreshToken`, `logout`).
 *
 * Механизм намеренно простой и не полагается на сессионный CSRF-token:
 *  1. обязательный кастомный заголовок `X-Requested-With: XMLHttpRequest`
 *     (cross-site HTML-форма не может его выставить без preflight);
 *  2. `Origin` (или `Referer` как fallback) обязан точно совпадать с одним
 *     из значений allowlist `CORS_ALLOW_ORIGIN`.
 *
 * Пустой allowlist запрещает всё. Ошибки не содержат значений cookie/token.
 */
final class CookieAuthRequestGuard
{
    public const REQUIRED_HEADER = 'X-Requested-With';
    public const REQUIRED_HEADER_VALUE = 'XMLHttpRequest';

    /** @param string[] $allowedOrigins */
    public function __construct(private readonly array $allowedOrigins)
    {
    }

    public function assertAllowed(Request $request): void
    {
        $customHeader = $request->headers->get(self::REQUIRED_HEADER);

        if (!is_string($customHeader) || strcasecmp($customHeader, self::REQUIRED_HEADER_VALUE) !== 0) {
            throw new AccessDeniedHttpException('account.cookie_auth.header_required');
        }

        $origin = $request->headers->get('Origin');

        if (!is_string($origin) || $origin === '') {
            $origin = $this->originFromReferer($request->headers->get('Referer'));
        }

        if ($origin === null || $origin === '' || !$this->isAllowed($origin)) {
            throw new AccessDeniedHttpException('account.cookie_auth.origin_denied');
        }
    }

    private function originFromReferer(?string $referer): ?string
    {
        if (!is_string($referer) || $referer === '') {
            return null;
        }

        $parts = parse_url($referer);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $parts['scheme'] . '://' . $parts['host'] . $port;
    }

    private function isAllowed(string $origin): bool
    {
        foreach ($this->allowedOrigins as $allowedOrigin) {
            if (is_string($allowedOrigin) && rtrim($allowedOrigin, '/') === rtrim($origin, '/')) {
                return true;
            }
        }

        return false;
    }
}
