<?php

declare(strict_types=1);

namespace App\Secrets\Business\Port;

/**
 * Выдаёт провалидированный ключевой материал по логическому назначению.
 *
 * AES-256-ключи возвращаются как 32 сырых байта; HMAC-ключи — как непустая
 * строка. Никакого усечения, паддинга или дефолтов: неверный формат — ошибка.
 */
interface EncryptionKeyProvider
{
    /**
     * @throws \App\Secrets\Business\Exception\InvalidKeyMaterial
     * @throws \App\Secrets\Business\Exception\SecretNotFound
     */
    public function getEncryptionKey(string $purpose): string;

    /**
     * @throws \App\Secrets\Business\Exception\InvalidKeyMaterial
     * @throws \App\Secrets\Business\Exception\SecretNotFound
     */
    public function getHmacKey(string $purpose): string;

    /**
     * Уникальные физические имена секретов, необходимых выбранному бэкенду.
     *
     * @return list<string>
     */
    public function requiredSecretNames(): array;

    /**
     * Проверяет существование и формат секрета по его физическому имени.
     *
     * @throws \App\Secrets\Business\Exception\SecretNotFound
     * @throws \App\Secrets\Business\Exception\InvalidKeyMaterial
     */
    public function validate(string $secretName): void;
}
