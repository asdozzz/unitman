<?php

declare(strict_types=1);

namespace App\AiChat\Business\Model;

use App\Account\Business\Model\OpenCode\ProviderSessionPolicy;
use App\Account\Business\Model\OpenCode\ProviderSessionTransport;

/**
 * Сессия AI-чата пользователя с провайдером.
 *
 * Агрегат хранит параметры подключения, срок жизни пользовательской сессии
 * (`expires_at`), provider-сессию (её идентификатор и срок жизни) и состояние
 * архивации. История сообщений живёт отдельно (`ai_chat_message`) и этой
 * моделью не изменяется. Все временные метки — unix-время в секундах.
 */
final class SessiyaAiChata
{
    public const TITLE_MAX_LENGTH = 256;
    public const MAX_MESSAGES = 500;
    public const PREVIEW_MAX_LENGTH = 200;

    private function __construct(
        private readonly string $id,
        private readonly string $accountId,
        private string $title,
        private readonly string $provider,
        private ?string $credentialId,
        private readonly string $model,
        private ?string $providerSessionId,
        private ?int $providerSessionExpiresAt,
        private readonly int $expiresAt,
        private ?int $archivedAt,
        private ?string $archiveReason,
        private readonly int $createdAt,
        private int $updatedAt,
    ) {
    }

    /**
     * Создать новую сессию с расчётом сроков жизни.
     *
     * `provider_session_expires_at` считается только для провайдеров с
     * реальным транспортом и ограничивается сверху `providerSessionMaxTtl`.
     * Для транспорта `None` provider-сессия не используется.
     */
    public static function create(
        string $id,
        string $accountId,
        string $provider,
        ?string $credentialId,
        string $model,
        int $now,
        ProviderSessionPolicy $policy,
        int $chatTtl,
        int $providerSessionMaxTtl,
        string $providerSessionId,
    ): self {
        $usesProviderSession = $policy->transport !== ProviderSessionTransport::None;

        return new self(
            $id,
            $accountId,
            '',
            $provider,
            $credentialId,
            $model,
            $usesProviderSession ? $providerSessionId : null,
            $usesProviderSession
                ? $now + min($policy->ttlSeconds, $providerSessionMaxTtl)
                : null,
            $now + $chatTtl,
            null,
            null,
            $now,
            $now,
        );
    }

    public static function restore(
        string $id,
        string $accountId,
        string $title,
        string $provider,
        ?string $credentialId,
        string $model,
        ?string $providerSessionId,
        ?int $providerSessionExpiresAt,
        int $expiresAt,
        ?int $archivedAt,
        ?string $archiveReason,
        int $createdAt,
        int $updatedAt,
    ): self {
        return new self(
            $id,
            $accountId,
            $title,
            $provider,
            $credentialId,
            $model,
            $providerSessionId,
            $providerSessionExpiresAt,
            $expiresAt,
            $archivedAt,
            $archiveReason,
            $createdAt,
            $updatedAt,
        );
    }

    /**
     * Обновить provider-сессию после ответа upstream.
     */
    public function aktualizirovatProviderSession(?string $providerSessionId, ?int $expiresAt): void
    {
        $this->providerSessionId = $providerSessionId;
        $this->providerSessionExpiresAt = $expiresAt;
    }

    /**
     * Архивировать сессию, если она ещё не архивирована.
     */
    public function arhivirovat(string $reason, int $now): void
    {
        if ($this->arhivirovana()) {
            return;
        }

        $this->archivedAt = $now;
        $this->archiveReason = $reason;
        $this->updatedAt = $now;
    }

    /**
     * Автоматически архивировать истёкшую сессию.
     *
     * @return bool true, если сессия была архивирована этим вызовом
     */
    public function arhivirovatPriIstechenii(int $now): bool
    {
        if ($this->arhivirovana() || !$this->istekla($now)) {
            return false;
        }

        $this->arhivirovat(PrichinaArhivaciiSessiiAiChata::Expired->value, $now);

        return true;
    }

    /**
     * Истекла ли provider-сессия. Без provider-сессии всегда false.
     */
    public function providerSessionIstekla(int $now): bool
    {
        if ($this->providerSessionExpiresAt === null) {
            return false;
        }

        return $now >= $this->providerSessionExpiresAt;
    }

    /**
     * Истекла ли пользовательская сессия (граница включительна).
     */
    public function istekla(int $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function arhivirovana(): bool
    {
        return $this->archivedAt !== null;
    }

    /**
     * Проверить владельца сессии.
     *
     * @throws \DomainException ключ `ai.chat.session_not_found`
     */
    public function trebovatVladelca(string $accountId): void
    {
        if ($this->accountId !== $accountId) {
            throw new \DomainException('ai.chat.session_not_found');
        }
    }

    /**
     * Сессия должна быть активной (не архивной и не истёкшей).
     *
     * @throws \DomainException `ai.chat.session_archived` или `ai.chat.session_expired`
     */
    public function trebovatAktivnuyu(int $now): void
    {
        if ($this->arhivirovana()) {
            throw new \DomainException('ai.chat.session_archived');
        }

        if ($this->istekla($now)) {
            throw new \DomainException('ai.chat.session_expired');
        }
    }

    /**
     * Назначить заголовок по первому сообщению пользователя.
     *
     * Заголовок ставится только один раз; текст обрезается по краям и до
     * `TITLE_MAX_LENGTH` символов (UTF-8).
     */
    public function naznachitZagolovokIzPervogoSoobsheniya(string $text, int $now): void
    {
        if ($this->title !== '') {
            return;
        }

        $normalized = trim($text);

        if ($normalized === '') {
            return;
        }

        $this->title = mb_substr($normalized, 0, self::TITLE_MAX_LENGTH);
        $this->updatedAt = $now;
    }

    public function obnovitAktivnost(int $now): void
    {
        $this->updatedAt = $now;
    }

    /**
     * Отвязать реквизиты провайдера: сессия становится архивной.
     */
    public function otvyazatRekvizity(int $now): void
    {
        $this->credentialId = null;

        $this->arhivirovat(PrichinaArhivaciiSessiiAiChata::CredentialDeleted->value, $now);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function credentialId(): ?string
    {
        return $this->credentialId;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function providerSessionId(): ?string
    {
        return $this->providerSessionId;
    }

    public function providerSessionExpiresAt(): ?int
    {
        return $this->providerSessionExpiresAt;
    }

    public function expiresAt(): int
    {
        return $this->expiresAt;
    }

    public function archivedAt(): ?int
    {
        return $this->archivedAt;
    }

    public function archiveReason(): ?string
    {
        return $this->archiveReason;
    }

    public function createdAt(): int
    {
        return $this->createdAt;
    }

    public function updatedAt(): int
    {
        return $this->updatedAt;
    }
}
