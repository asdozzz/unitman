<?php

declare(strict_types=1);

namespace App\Account\Business\Model\OpenCode;

/**
 * Реквизиты подключения к OpenCode (URL, модель, API-ключ).
 *
 * Секрет хранится ТОЛЬКО в зашифрованном виде. Доменная модель не умеет
 * шифровать/расшифровывать — этим занимаются UseCase через порт
 * `UmeetShifrovatSekrety`, чтобы Business не зависел от ext-openssl.
 */
final class OpenCodeCredential
{
    public const CIPHER_VERSION = 1;

    private function __construct(
        private readonly string $id,
        private readonly string $accountId,
        private string $name,
        private string $url,
        private string $model,
        private string $apiKeyCipher,
        private string $apiKeyHint,
        private readonly int $cipherVersion,
        private readonly int $createdAt,
        private int $updatedAt,
    ) {
    }

    public static function create(
        string $id,
        string $accountId,
        string $name,
        string $url,
        string $model,
        string $apiKeyCipher,
        string $apiKeyHint,
        int $now,
    ): self {
        if ($id === '') {
            throw new \DomainException('account.opencode_credential.empty_id');
        }

        if ($accountId === '') {
            throw new \DomainException('account.opencode_credential.empty_account_id');
        }

        if ($apiKeyCipher === '') {
            throw new \DomainException('account.opencode_credential.empty_api_key');
        }

        $credential = new self(
            $id,
            $accountId,
            self::normalizeName($name),
            self::normalizeUrl($url),
            self::normalizeModel($model),
            $apiKeyCipher,
            $apiKeyHint,
            self::CIPHER_VERSION,
            $now,
            $now,
        );

        return $credential;
    }

    public static function restore(
        string $id,
        string $accountId,
        string $name,
        string $url,
        string $model,
        string $apiKeyCipher,
        string $apiKeyHint,
        int $cipherVersion,
        int $createdAt,
        int $updatedAt,
    ): self {
        return new self(
            $id,
            $accountId,
            $name,
            $url,
            $model,
            $apiKeyCipher,
            $apiKeyHint,
            $cipherVersion,
            $createdAt,
            $updatedAt,
        );
    }

    public function izmenit(
        ?string $name,
        ?string $url,
        ?string $model,
        ?string $apiKeyCipher,
        ?string $apiKeyHint,
        int $now,
    ): void {
        if ($name !== null) {
            $this->name = self::normalizeName($name);
        }

        if ($url !== null) {
            $this->url = self::normalizeUrl($url);
        }

        if ($model !== null) {
            $this->model = self::normalizeModel($model);
        }

        if ($apiKeyCipher !== null) {
            if ($apiKeyCipher === '') {
                throw new \DomainException('account.opencode_credential.empty_api_key');
            }

            $this->apiKeyCipher = $apiKeyCipher;
            $this->apiKeyHint = $apiKeyHint ?? $this->apiKeyHint;
        }

        $this->updatedAt = $now;
    }

    public function prinadlezhit(string $accountId): bool
    {
        return $this->accountId === $accountId;
    }

    public function trebovatVladelca(string $accountId): void
    {
        if (!$this->prinadlezhit($accountId)) {
            // Намеренно не раскрываем существование чужой записи.
            throw new \DomainException('account.opencode_credential.not_found');
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getApiKeyCipher(): string
    {
        return $this->apiKeyCipher;
    }

    public function getApiKeyHint(): string
    {
        return $this->apiKeyHint;
    }

    public function getCipherVersion(): int
    {
        return $this->cipherVersion;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    /**
     * Дополнительные аутентифицируемые данные (AAD) для AES-GCM.
     *
     * Привязывает шифротекст к конкретной записи и владельцу: подмена строки
     * в БД (или перенос шифротекста в чужую запись) приводит к ошибке
     * расшифровки, а не к тихой подстановке чужого ключа.
     */
    public static function makeAad(string $accountId, string $credentialId): string
    {
        return $accountId . ':' . $credentialId;
    }

    /**
     * Маска для показа пользователю: последние 4 символа, остальное скрыто.
     */
    public static function makeHint(string $apiKey): string
    {
        $length = \strlen($apiKey);

        if ($length === 0) {
            return '';
        }

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return '****' . substr($apiKey, -4);
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new \DomainException('account.opencode_credential.empty_name');
        }

        if (\strlen($name) > 128) {
            throw new \DomainException('account.opencode_credential.name_too_long');
        }

        return $name;
    }

    private static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            throw new \DomainException('account.opencode_credential.empty_url');
        }

        if (\strlen($url) > 512) {
            throw new \DomainException('account.opencode_credential.url_too_long');
        }

        return $url;
    }

    private static function normalizeModel(string $model): string
    {
        $model = trim($model);

        if ($model === '') {
            throw new \DomainException('account.opencode_credential.empty_model');
        }

        if (\strlen($model) > 128) {
            throw new \DomainException('account.opencode_credential.model_too_long');
        }

        return $model;
    }
}
