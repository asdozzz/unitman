<?php

declare(strict_types=1);

namespace App\Runner\Business\Utils;

use App\Secrets\Business\Exception\InvalidKeyMaterial;
use App\Secrets\Business\Exception\SecretNotFound;
use App\Secrets\Business\Model\KeyPurpose;
use App\Secrets\Business\Port\EncryptionKeyProvider;
use App\Secrets\Infra\Adapter\ArraySecretProvider;
use App\Secrets\Infra\Adapter\SecretEncryptionKeyProvider;

/**
 * Детерминированный секрет раннера (контракт runner registration v2).
 *
 * `secret = hash_hmac('sha256', runnerId, RUNNER_SECRET_MASTER_KEY)`.
 *
 * Master key приходит только из `EncryptionKeyProvider`. Fallback на
 * APP_SECRET удалён полностью: отсутствие/пустое значение — fail-closed
 * исключение (`runner_secret.master_key_not_configured`). Алгоритм и
 * деривация не изменились, поэтому уже выданные секреты продолжают
 * проверяться при том же master key.
 *
 * Секрет не хранится в БД и не логируется: он вычисляется заново при каждой
 * проверке подписи и отдаётся раннеру ровно один раз при регистрации.
 */
final class RunnerSecretGenerator
{
    public function __construct(private readonly EncryptionKeyProvider $keys)
    {
    }

    /**
     * Фабрика для тестов/точечной сборки с явным ключом.
     *
     * @internal Production-DI передаёт провайдер.
     */
    public static function fromMasterKey(string $masterKey): self
    {
        return new self(new SecretEncryptionKeyProvider(
            new ArraySecretProvider(['RUNNER_SECRET_MASTER_KEY' => $masterKey]),
        ));
    }

    public function generate(string $runnerId): string
    {
        return hash_hmac('sha256', $runnerId, $this->resolveMasterKey());
    }

    private function resolveMasterKey(): string
    {
        try {
            $masterKey = trim($this->keys->getHmacKey(KeyPurpose::RUNNER_SECRETS));
        } catch (SecretNotFound $e) {
            throw new \DomainException('runner_secret.master_key_not_configured', 0, $e);
        } catch (InvalidKeyMaterial $e) {
            throw new \DomainException('runner_secret.invalid_master_key', 0, $e);
        }

        if ($masterKey === '') {
            throw new \DomainException('runner_secret.master_key_not_configured');
        }

        return $masterKey;
    }
}
