<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\Model;

/**
 * Unit-сессия OpenCode: диалог, жёстко привязанный к одному юниту.
 *
 * Агрегат не знает о workspace: у него нет `tip`, вложенного workspace и
 * списка юнитов. Один юнит — один рабочий каталог хода.
 */
final class UnitSession
{
    private const TITLE_MAX_LENGTH = 256;

    public const ARCHIVE_REASON_UNIT_DELETED = 'unit_deleted';

    public const ARCHIVE_REASON_MANUAL = 'manual';

    private function __construct(
        private string $id,
        private string $accountId,
        private string $credentialId,
        private string $title,
        private ?string $externalSessionId,
        private string $unitId,
        private string $unitProjectId,
        private ?int $archivedAt,
        private ?string $archiveReason,
        private int $createdAt,
        private int $updatedAt,
        private ?string $model = null,
    ) {
    }

    public static function create(
        string $id,
        string $accountId,
        string $credentialId,
        ?string $title,
        string $unitId,
        string $unitProjectId,
        int $now,
        ?string $model = null,
    ): self {
        self::trebovatNepustoe($accountId, 'opencode.session.account_required');
        self::trebovatNepustoe($credentialId, 'opencode.session.credential_required');
        self::trebovatNepustoe($unitId, 'opencode.session.unit_required');
        self::trebovatNepustoe($unitProjectId, 'opencode.session.unit_project_required');

        return new self(
            $id,
            $accountId,
            $credentialId,
            self::normalizeTitle($title),
            null,
            $unitId,
            $unitProjectId,
            null,
            null,
            $now,
            $now,
            $model,
        );
    }

    public static function restore(
        string $id,
        string $accountId,
        string $credentialId,
        string $title,
        ?string $externalSessionId,
        string $unitId,
        string $unitProjectId,
        ?int $archivedAt,
        ?string $archiveReason,
        int $createdAt,
        int $updatedAt,
        ?string $model = null,
    ): self {
        if ($archivedAt !== null && ($archiveReason === null || trim($archiveReason) === '')) {
            throw new \DomainException('opencode.session.archive_reason_required');
        }

        return new self(
            $id,
            $accountId,
            $credentialId,
            $title,
            $externalSessionId,
            $unitId,
            $unitProjectId,
            $archivedAt,
            $archiveReason,
            $createdAt,
            $updatedAt,
            $model,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getCredentialId(): string
    {
        return $this->credentialId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getExternalSessionId(): ?string
    {
        return $this->externalSessionId;
    }

    public function getUnitId(): string
    {
        return $this->unitId;
    }

    public function getUnitProjectId(): string
    {
        return $this->unitProjectId;
    }

    public function getArchivedAt(): ?int
    {
        return $this->archivedAt;
    }

    public function getArchiveReason(): ?string
    {
        return $this->archiveReason;
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
     * Модель, выбранная при создании сессии. `null` — взять дефолт реквизитов
     * при следующем ходе.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    public function estArhiv(): bool
    {
        return $this->archivedAt !== null;
    }

    public function prinadlezhit(string $accountId): bool
    {
        return $this->accountId === $accountId;
    }

    public function trebovatAktivnuyu(): void
    {
        if ($this->estArhiv()) {
            throw new \DomainException('opencode.session.archived');
        }
    }

    public function arhivirovat(string $reason, int $now): void
    {
        if ($this->estArhiv()) {
            return;
        }

        if (trim($reason) === '') {
            throw new \DomainException('opencode.session.archive_reason_required');
        }

        $this->archivedAt = $now;
        $this->archiveReason = trim($reason);
        $this->updatedAt = $now;
    }

    public function zadatZagolovok(?string $title, int $now): void
    {
        $normalized = self::normalizeTitle($title);

        if ($normalized === '') {
            return;
        }

        $this->title = $normalized;
        $this->updatedAt = $now;
    }

    /**
     * Заголовок по первому промпту — ровно один раз.
     */
    public function podpisatPoPromptu(string $prompt, int $now): void
    {
        if ($this->title !== '') {
            return;
        }

        $this->title = self::normalizeTitle($prompt);
        $this->updatedAt = $now;
    }

    public function zapomnitVneshnyuyuSessiyu(string $externalSessionId, int $now): void
    {
        $this->externalSessionId = $externalSessionId;
        $this->updatedAt = $now;
    }

    public function otmetitIzmenenie(int $now): void
    {
        $this->updatedAt = $now;
    }

    private static function trebovatNepustoe(string $value, string $errorKey): void
    {
        if (trim($value) === '') {
            throw new \DomainException($errorKey);
        }
    }

    private static function normalizeTitle(?string $title): string
    {
        if ($title === null) {
            return '';
        }

        return mb_substr(trim($title), 0, self::TITLE_MAX_LENGTH);
    }
}
