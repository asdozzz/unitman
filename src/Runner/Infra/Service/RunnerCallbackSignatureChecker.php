<?php

declare(strict_types=1);

namespace App\Runner\Infra\Service;

/**
 * Проверка подписи callback-запросов Golang-раннера.
 *
 * `saveSteps` — публичный эндпоинт без JWT (см. `config/packages/security.yaml`),
 * поэтому единственная защита от подделки шагов — HMAC-SHA256 на общем секрете.
 * Раннер присылает заголовок `X-Runner-Signature: <hex>`.
 *
 * Если секрет не сконфигурирован (пустая строка), проверка отключается и
 * выводится предупреждение — так сохраняется совместимость с уже
 * развёрнутыми раннерами на время поэтапного выката.
 */
final class RunnerCallbackSignatureChecker
{
    public const HEADER_NAME = 'X-Runner-Signature';

    public function __construct(private readonly string $runnerCallbackSecret)
    {
    }

    public function estPodpisValidna(string $token, string $stepsContent, ?string $signature): bool
    {
        if ($this->runnerCallbackSecret === '') {
            return true;
        }

        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $token . $stepsContent, $this->runnerCallbackSecret);

        return hash_equals($expected, $signature);
    }
}
