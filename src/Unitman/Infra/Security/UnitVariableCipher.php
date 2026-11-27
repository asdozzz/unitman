<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Security;

use App\Secrets\Business\Exception\InvalidKeyMaterial;
use App\Secrets\Business\Exception\SecretNotFound;
use App\Secrets\Business\Model\KeyPurpose;
use App\Secrets\Business\Port\EncryptionKeyProvider;

/**
 * AES-256-GCM для hidden-переменных юнита в Temporal payload.
 *
 * Ключ выводится детерминированно из per-runner секрета:
 *   runnerSecret = HMAC-SHA256(runnerId, RUNNER_SECRET_MASTER_KEY)
 *   key          = HKDF-SHA256(runnerSecret, info = "unitman-unit-variables-v1")
 *
 * Раннер получает тот же runnerSecret из config.json и выводит тот же ключ.
 * В Temporal payload попадает только шифротекст; plaintext не сериализуется
 * и не логируется. AAD привязывает шифротекст к unitId:variableId.
 */
final class UnitVariableCipher
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const KEY_LENGTH = 32;
    private const HKDF_INFO = 'unitman-unit-variables-v1';

    public function __construct(private readonly EncryptionKeyProvider $keys)
    {
    }

    public function encrypt(string $plain, string $unitId, string $variableId, string $runnerId): string
    {
        $key = $this->keyForRunner($runnerId);
        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plain,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $this->aad($unitId, $variableId),
            self::TAG_LENGTH,
        );

        if ($ciphertext === false) {
            throw new \DomainException('unit.variables.encrypt_failed');
        }

        return base64_encode($nonce . $tag . $ciphertext);
    }

    public function decrypt(string $cipher, string $unitId, string $variableId, string $runnerId): string
    {
        $key = $this->keyForRunner($runnerId);
        $raw = base64_decode($cipher, true);

        if ($raw === false || strlen($raw) < self::NONCE_LENGTH + self::TAG_LENGTH) {
            throw new \DomainException('unit.variables.decrypt_failed');
        }

        $nonce = substr($raw, 0, self::NONCE_LENGTH);
        $tag = substr($raw, self::NONCE_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::NONCE_LENGTH + self::TAG_LENGTH);

        $plain = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $this->aad($unitId, $variableId),
        );

        if ($plain === false) {
            throw new \DomainException('unit.variables.decrypt_failed');
        }

        return $plain;
    }

    private function keyForRunner(string $runnerId): string
    {
        $runnerId = trim($runnerId);

        if ($runnerId === '') {
            throw new \DomainException('unit.variables.runner_id_empty');
        }

        try {
            $masterKey = $this->keys->getHmacKey(KeyPurpose::UNIT_VARIABLES);
        } catch (SecretNotFound $e) {
            throw new \DomainException('unit.variables.master_key_not_configured', 0, $e);
        } catch (InvalidKeyMaterial $e) {
            throw new \DomainException('unit.variables.invalid_master_key', 0, $e);
        }

        // Тот же per-runner секрет, что backend выдаёт при регистрации и
        // проверяет в callback-подписях.
        $runnerSecret = hash_hmac('sha256', $runnerId, $masterKey);

        return hash_hkdf('sha256', $runnerSecret, self::KEY_LENGTH, self::HKDF_INFO);
    }

    private function aad(string $unitId, string $variableId): string
    {
        return $unitId . ':' . $variableId;
    }
}
