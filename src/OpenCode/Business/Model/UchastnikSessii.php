<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Участник workspace-сессии OpenCode.
 *
 * Владелец сессии (`SessiyaOpenCode::$accountId`) всегда имеет строку с ролью
 * `owner` — это инвариант, который поддерживают use case и миграция. Роль
 * `owner` не меняется через обычное редактирование: передача владения —
 * отдельная явная операция, иначе можно остаться без управляющего сессией.
 */
final class UchastnikSessii
{
    private const ACCOUNT_ID_MAX_LENGTH = 128;

    private const CREDENTIAL_ID_MAX_LENGTH = 64;

    private const MODEL_MAX_LENGTH = 128;

    private function __construct(
        private readonly string $sessionId,
        private readonly string $accountId,
        private RolUchastnikaSessii $rol,
        private ?string $defaultCredentialId,
        private ?string $defaultModel,
        private readonly ?string $invitedBy,
        private readonly int $createdAt,
        private int $updatedAt,
    ) {
    }

    public static function create(
        string $sessionId,
        string $accountId,
        RolUchastnikaSessii $rol,
        ?string $defaultCredentialId,
        ?string $defaultModel,
        ?string $invitedBy,
        int $now,
    ): self {
        $sessionId = trim($sessionId);
        $accountId = trim($accountId);

        if ($sessionId === '') {
            throw new \DomainException('opencode.participant.session_required');
        }

        if ($accountId === '') {
            throw new \DomainException('opencode.participant.account_required');
        }

        if (\strlen($accountId) > self::ACCOUNT_ID_MAX_LENGTH) {
            throw new \DomainException('opencode.participant.account_too_long');
        }

        $invitedBy = $invitedBy !== null ? trim($invitedBy) : null;

        return new self(
            $sessionId,
            $accountId,
            $rol,
            self::normalizeCredentialId($defaultCredentialId),
            self::normalizeModel($defaultModel),
            $invitedBy === '' ? null : $invitedBy,
            $now,
            $now,
        );
    }

    public static function restore(
        string $sessionId,
        string $accountId,
        RolUchastnikaSessii $rol,
        ?string $defaultCredentialId,
        ?string $defaultModel,
        ?string $invitedBy,
        int $createdAt,
        int $updatedAt,
    ): self {
        return new self(
            $sessionId,
            $accountId,
            $rol,
            $defaultCredentialId,
            $defaultModel,
            $invitedBy,
            $createdAt,
            $updatedAt,
        );
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getRol(): RolUchastnikaSessii
    {
        return $this->rol;
    }

    public function getDefaultCredentialId(): ?string
    {
        return $this->defaultCredentialId;
    }

    public function getDefaultModel(): ?string
    {
        return $this->defaultModel;
    }

    public function getInvitedBy(): ?string
    {
        return $this->invitedBy;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    public function estVladelec(): bool
    {
        return $this->rol === RolUchastnikaSessii::OWNER;
    }

    public function mozhetPisat(): bool
    {
        return $this->rol->mozhetPisat();
    }

    public function mozhetUpravlyat(): bool
    {
        return $this->rol->mozhetUpravlyat();
    }

    /**
     * Владельца нельзя удалить или понизить обычным редактированием: сессия
     * осталась бы без управляющего.
     *
     * @throws \DomainException `opencode.participant.owner_role_immutable`
     */
    public function izmenitRol(RolUchastnikaSessii $rol, int $now): void
    {
        if ($rol === $this->rol) {
            return;
        }

        if ($this->estVladelec() || $rol === RolUchastnikaSessii::OWNER) {
            throw new \DomainException('opencode.participant.owner_role_immutable');
        }

        $this->rol = $rol;
        $this->updatedAt = $now;
    }

    /**
     * Реквизиты по умолчанию — только для ходов этого участника: бюджет
     * всегда списывается с автора, а не с владельца сессии.
     */
    public function izmenitRekvizity(?string $defaultCredentialId, ?string $defaultModel, int $now): void
    {
        $this->defaultCredentialId = self::normalizeCredentialId($defaultCredentialId);
        $this->defaultModel = self::normalizeModel($defaultModel);
        $this->updatedAt = $now;
    }

    /**
     * @throws \DomainException `opencode.participant.owner_cannot_be_removed`
     */
    public function trebovatMozhnoUdalit(): void
    {
        if ($this->estVladelec()) {
            throw new \DomainException('opencode.participant.owner_cannot_be_removed');
        }
    }

    public function otmetitIzmenenie(int $now): void
    {
        $this->updatedAt = $now;
    }

    private static function normalizeCredentialId(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (\strlen($value) > self::CREDENTIAL_ID_MAX_LENGTH) {
            throw new \DomainException('opencode.participant.credential_too_long');
        }

        return $value;
    }

    private static function normalizeModel(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (\strlen($value) > self::MODEL_MAX_LENGTH) {
            throw new \DomainException('opencode.participant.model_too_long');
        }

        return $value;
    }
}
