<?php

declare(strict_types=1);

namespace App\Runner\Business\Utils;

/**
 * Строгая проверка публичного адреса раннера (контракт v2, A2).
 *
 * Адрес обязан быть абсолютным URL со схемой `http`/`https`, непустым
 * host и без userinfo (`user:pass@`). Query/fragment допустимы: проверка
 * доступности запрашивает адрес как есть (`GET <publicAddress>`).
 */
final class RunnerPublicAddressValidator
{
    public const INVALID_CODE = 'runner.public_address.invalid';

    /**
     * @throws \InvalidArgumentException `runner.public_address.invalid`
     */
    public function validate(string $rawPublicAddress): string
    {
        $value = trim($rawPublicAddress);

        if ($value === '' || preg_match('/\s/', $value) === 1) {
            throw $this->invalid();
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            throw $this->invalid();
        }

        $parts = parse_url($value);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw $this->invalid();
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw $this->invalid();
        }

        if (trim((string) $parts['host']) === '') {
            throw $this->invalid();
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw $this->invalid();
        }

        return rtrim($value, '/');
    }

    private function invalid(): \InvalidArgumentException
    {
        return new \InvalidArgumentException(self::INVALID_CODE);
    }
}
