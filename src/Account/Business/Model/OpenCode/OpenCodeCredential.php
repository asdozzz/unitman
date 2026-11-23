<?php

declare(strict_types=1);

namespace App\Account\Business\Model\OpenCode;

/**
 * Реквизиты подключения к OpenCode: провайдер, базовый URL, набор моделей,
 * дефолтная модель и API-ключ.
 *
 * Секрет хранится ТОЛЬКО в зашифрованном виде. Доменная модель не умеет
 * шифровать/расшифровывать — этим занимаются UseCase через порт
 * `UmeetShifrovatSekrety`, чтобы Business не зависел от ext-openssl.
 *
 * Инварианты набора моделей: от 1 до 50 штук, каждая непустая и не длиннее
 * 128 символов, без дубликатов; дефолтная модель обязана входить в набор.
 * `getModel()` возвращает дефолтную модель для легаси-потребителей.
 */
final class OpenCodeCredential
{
    public const CIPHER_VERSION = 1;

    public const MAX_MODELS = 50;
    public const MAX_MODEL_LENGTH = 128;

    /**
     * @param list<string> $models
     */
    private function __construct(
        private readonly string $id,
        private readonly string $accountId,
        private string $name,
        private string $provider,
        private string $url,
        private array $models,
        private string $defaultModel,
        private string $apiKeyCipher,
        private string $apiKeyHint,
        private readonly int $cipherVersion,
        private readonly int $createdAt,
        private int $updatedAt,
    ) {
    }

    /**
     * @param list<string> $models
     */
    public static function create(
        string $id,
        string $accountId,
        string $name,
        string $provider,
        string $url,
        array $models,
        string $defaultModel,
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

        $normalizedModels = self::normalizeModels($models);
        $normalizedDefault = self::resolveDefaultModel($normalizedModels, $defaultModel);

        return new self(
            $id,
            $accountId,
            self::normalizeName($name),
            self::normalizeProvider($provider),
            self::normalizeUrl($url),
            $normalizedModels,
            $normalizedDefault,
            $apiKeyCipher,
            $apiKeyHint,
            self::CIPHER_VERSION,
            $now,
            $now,
        );
    }

    /**
     * @param list<string> $models
     */
    public static function restore(
        string $id,
        string $accountId,
        string $name,
        string $provider,
        string $url,
        array $models,
        string $defaultModel,
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
            $provider,
            $url,
            $models,
            $defaultModel,
            $apiKeyCipher,
            $apiKeyHint,
            $cipherVersion,
            $createdAt,
            $updatedAt,
        );
    }

    /**
     * Изменение реквизитов. `null` в любом поле (кроме `$now`) означает
     * «оставить как есть».
     *
     * @param list<string>|null $models
     */
    public function izmenit(
        ?string $name,
        ?string $provider,
        ?string $url,
        ?array $models,
        ?string $defaultModel,
        ?string $apiKeyCipher,
        ?string $apiKeyHint,
        int $now,
    ): void {
        if ($name !== null) {
            $this->name = self::normalizeName($name);
        }

        if ($provider !== null) {
            $this->provider = self::normalizeProvider($provider);
        }

        if ($url !== null) {
            $this->url = self::normalizeUrl($url);
        }

        $newModels = $models !== null ? self::normalizeModels($models) : $this->models;

        if ($defaultModel !== null) {
            $candidate = trim($defaultModel);

            if ($candidate === '') {
                $candidate = $newModels[0];
            }

            if (!\in_array($candidate, $newModels, true)) {
                throw new \DomainException('account.opencode_credential.model_not_available');
            }

            $this->models = $newModels;
            $this->defaultModel = $candidate;
        } elseif (\in_array($this->defaultModel, $newModels, true)) {
            $this->models = $newModels;
        } else {
            // Набор моделей заменён, а прежний дефолт в него не входит —
            // аккуратно переключаемся на первую модель нового набора.
            $this->models = $newModels;
            $this->defaultModel = $newModels[0];
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

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return list<string>
     */
    public function getModels(): array
    {
        return $this->models;
    }

    public function getDefaultModel(): string
    {
        return $this->defaultModel;
    }

    /**
     * Синоним дефолтной модели для легаси-потребителей.
     */
    public function getModel(): string
    {
        return $this->defaultModel;
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

    private static function normalizeProvider(string $provider): string
    {
        return ProvayderOpenCode::izKoda($provider)->value;
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

    /**
     * @param list<string> $models
     *
     * @return list<string>
     */
    private static function normalizeModels(array $models): array
    {
        if ($models === []) {
            throw new \DomainException('account.opencode_credential.empty_models');
        }

        if (\count($models) > self::MAX_MODELS) {
            throw new \DomainException('account.opencode_credential.too_many_models');
        }

        $normalized = [];
        $seen = [];

        foreach ($models as $model) {
            $model = trim($model);

            if ($model === '') {
                throw new \DomainException('account.opencode_credential.empty_model');
            }

            if (\strlen($model) > self::MAX_MODEL_LENGTH) {
                throw new \DomainException('account.opencode_credential.model_too_long');
            }

            if (isset($seen[$model])) {
                throw new \DomainException('account.opencode_credential.duplicate_model');
            }

            $seen[$model] = true;
            $normalized[] = $model;
        }

        return $normalized;
    }

    /**
     * @param list<string> $models
     */
    private static function resolveDefaultModel(array $models, string $defaultModel): string
    {
        $defaultModel = trim($defaultModel);

        if ($defaultModel === '') {
            return $models[0];
        }

        if (!\in_array($defaultModel, $models, true)) {
            throw new \DomainException('account.opencode_credential.model_not_available');
        }

        return $defaultModel;
    }
}
