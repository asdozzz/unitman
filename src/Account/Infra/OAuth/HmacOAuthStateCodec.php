<?php

declare(strict_types=1);

namespace App\Account\Infra\OAuth;

use App\Account\Business\Port\CanSignOAuthState;

final class HmacOAuthStateCodec implements CanSignOAuthState
{
    public function __construct(private readonly string $secret)
    {
    }

    public function podpisat(array $payload): string
    {
        $encoded = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encoded, $this->secret);

        return $encoded . '.' . $signature;
    }

    public function proverit(string $state): array
    {
        $parts = explode('.', $state);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new \DomainException('oauth.state.invalid');
        }

        [$encoded, $signature] = $parts;
        $expected = hash_hmac('sha256', $encoded, $this->secret);

        if (!hash_equals($expected, $signature)) {
            throw new \DomainException('oauth.state.invalid');
        }

        $json = $this->base64UrlDecode($encoded);
        $payload = json_decode($json, true);

        if (!is_array($payload)) {
            throw new \DomainException('oauth.state.invalid');
        }

        $expiresAt = (int) ($payload['exp'] ?? 0);

        if ($expiresAt < time()) {
            throw new \DomainException('oauth.state.expired');
        }

        return $payload;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new \DomainException('oauth.state.invalid');
        }

        return $decoded;
    }
}
