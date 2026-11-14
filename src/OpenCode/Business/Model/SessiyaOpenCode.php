<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Model;

/**
 * Сессия диалога: связка «пользователь + реквизиты OpenCode + история ходов».
 *
 * Сессия не хранит API-ключ — только идентификатор реквизитов в Account,
 * поэтому удаление реквизитов не компрометирует историю сессии.
 */
final class SessiyaOpenCode
{
    private const TITLE_MAX_LENGTH = 256;

    private function __construct(
        private string $id,
        private string $accountId,
        private string $credentialId,
        private string $title,
        private ?string $externalSessionId,
        private int $createdAt,
        private int $updatedAt,
    ) {
    }

    public static function create(
        string $id,
        string $accountId,
        string $credentialId,
        ?string $title,
        int $now,
    ): self {
        if (trim($accountId) === '') {
            throw new \DomainException('opencode.session.account_required');
        }

        if (trim($credentialId) === '') {
            throw new \DomainException('opencode.session.credential_required');
        }

        return new self(
            $id,
            $accountId,
            $credentialId,
            self::normalizeTitle($title),
            null,
            $now,
            $now,
        );
    }

    public static function restore(
        string $id,
        string $accountId,
        string $credentialId,
        string $title,
        ?string $externalSessionId,
        int $createdAt,
        int $updatedAt,
    ): self {
        return new self($id, $accountId, $credentialId, $title, $externalSessionId, $createdAt, $updatedAt);
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

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    public function prinadlezhit(string $accountId): bool
    {
        return $this->accountId === $accountId;
    }

    /**
     * @throws \DomainException если сессия принадлежит другому пользователю
     */
    public function trebovatVladelca(string $accountId): void
    {
        if (!$this->prinadlezhit($accountId)) {
            throw new \DomainException('opencode.session.not_found');
        }
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
     * Заголовок по умолчанию — по первому промпту, ровно один раз.
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

    private static function normalizeTitle(?string $title): string
    {
        if ($title === null) {
            return '';
        }

        return mb_substr(trim($title), 0, self::TITLE_MAX_LENGTH);
    }
}