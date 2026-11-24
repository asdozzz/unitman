<?php

declare(strict_types=1);

namespace App\Runner\Business\Utils;

/**
 * Детерминированный секрет раннера (контракт runner registration v2).
 *
 * `secret = hash_hmac('sha256', runnerId, masterKey)`:
 * - masterKey берётся из `RUNNER_SECRET_MASTER_KEY`;
 * - если переменная не задана или пуста — используется `APP_SECRET` (fallback);
 * - секрет не хранится в БД и не логируется: он вычисляется заново при каждой
 *   проверке подписи и отдаётся раннеру ровно один раз при регистрации.
 */
final class RunnerSecretGenerator
{
    public function __construct(
        private readonly ?string $masterKey = null,
        private readonly ?string $appSecret = null,
    ) {
    }

    public function generate(string $runnerId): string
    {
        return hash_hmac('sha256', $runnerId, $this->resolveMasterKey());
    }

    /**
     * Пустой или пробельный RUNNER_SECRET_MASTER_KEY означает fallback на
     * APP_SECRET. Пустая строка возвращается, только если не задано и это:
     * hash_hmac всё равно детерминирован, а APP_SECRET присутствует во всех
     * стендах Symfony.
     */
    private function resolveMasterKey(): string
    {
        $masterKey = $this->masterKey !== null ? trim($this->masterKey) : '';

        if ($masterKey !== '') {
            return $masterKey;
        }

        return $this->appSecret !== null ? trim($this->appSecret) : '';
    }
}
