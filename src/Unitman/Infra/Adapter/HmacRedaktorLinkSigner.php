<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Adapter;

use App\Unitman\Business\Port\Redaktor\UmeetPodpisivatSsylkuRedaktora;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * HMAC-SHA256 подпись персональной ссылки редактора.
 *
 * Формат: `base64url(payloadJson).base64url(hmac(payload))`. Секрет — тот же
 * `kernel.secret` приложения; новых зависимостей не требуется.
 */
final class HmacRedaktorLinkSigner implements UmeetPodpisivatSsylkuRedaktora
{
    private const MODES = ['view', 'edit', 'locked'];

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    )
    {
    }

    public function podpisat(string $unitId, string $userId, string $mode, int $ttlSeconds): string
    {
        if (!in_array($mode, self::MODES, true)) {
            throw new \DomainException('unit.redaktor.ssylka_nevalidna');
        }

        $payload = [
            'unitId' => $unitId,
            'userId' => $userId,
            'mode' => $mode,
            'exp' => time() + max(1, $ttlSeconds),
        ];

        $encodedPayload = $this->encode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->encode(hash_hmac('sha256', $encodedPayload, $this->secret, true));

        return $encodedPayload . '.' . $signature;
    }

    public function proverit(string $ssylka): array
    {
        $parts = explode('.', $ssylka);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \DomainException('unit.redaktor.ssylka_nevalidna');
        }

        [$encodedPayload, $signature] = $parts;

        $expected = $this->encode(hash_hmac('sha256', $encodedPayload, $this->secret, true));

        if (!hash_equals($expected, $signature)) {
            throw new \DomainException('unit.redaktor.ssylka_nevalidna');
        }

        $decoded = $this->decode($encodedPayload);

        if ($decoded === null) {
            throw new \DomainException('unit.redaktor.ssylka_nevalidna');
        }

        $payload = json_decode($decoded, true);

        if (!is_array($payload)
            || !isset($payload['unitId'], $payload['userId'], $payload['mode'], $payload['exp'])
            || !is_string($payload['unitId'])
            || !is_string($payload['userId'])
            || !is_string($payload['mode'])
            || !is_int($payload['exp'])
            || !in_array($payload['mode'], self::MODES, true)
        ) {
            throw new \DomainException('unit.redaktor.ssylka_nevalidna');
        }

        if ($payload['exp'] < time()) {
            throw new \DomainException('unit.redaktor.ssylka_istekla');
        }

        return [
            'unitId' => $payload['unitId'],
            'userId' => $payload['userId'],
            'mode' => $payload['mode'],
            'exp' => $payload['exp'],
        ];
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): ?string
    {
        $padding = strlen($value) % 4;

        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
