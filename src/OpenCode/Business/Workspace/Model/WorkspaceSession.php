<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Workspace\Model;

use App\OpenCode\Business\Model\ProektWorkspace;
use App\OpenCode\Business\Model\Workspace;

/**
 * Workspace-сессия OpenCode: самостоятельный агрегат.
 *
 * Не наследует и не содержит unit-сессию. Идентификатор workspace — это его
 * собственный `id`; ходы и уведомления хранятся в отдельных таблицах.
 */
final class WorkspaceSession
{
    private const NAME_MAX_LENGTH = 256;

    /**
     * @param list<ProektWorkspace> $projects
     */
    private function __construct(
        private string $id,
        private string $accountId,
        private string $credentialId,
        private string $name,
        private ?string $externalSessionId,
        private ?int $archivedAt,
        private ?string $archiveReason,
        private Workspace $workspace,
        private int $createdAt,
        private int $updatedAt,
    ) {
    }

    /**
     * @param list<ProektWorkspace> $projects
     */
    public static function create(
        string $id,
        string $accountId,
        string $credentialId,
        string $name,
        string $branch,
        array $projects,
        string $initialPrompt,
        int $now,
    ): self {
        if (trim($accountId) === '') {
            throw new \DomainException('opencode.session.account_required');
        }

        if (trim($credentialId) === '') {
            throw new \DomainException('opencode.session.credential_required');
        }

        $name = trim($name);

        if ($name === '') {
            throw new \DomainException('opencode.workspace.name_required');
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new \DomainException('opencode.workspace.name_too_long');
        }

        return new self(
            $id,
            $accountId,
            $credentialId,
            $name,
            null,
            null,
            null,
            Workspace::create($branch, $initialPrompt, $projects, $now),
            $now,
            $now,
        );
    }

    public static function restore(
        string $id,
        string $accountId,
        string $credentialId,
        string $name,
        ?string $externalSessionId,
        ?int $archivedAt,
        ?string $archiveReason,
        Workspace $workspace,
        int $createdAt,
        int $updatedAt,
    ): self {
        if ($archivedAt !== null && ($archiveReason === null || trim($archiveReason) === '')) {
            throw new \DomainException('opencode.session.archive_reason_required');
        }

        return new self(
            $id,
            $accountId,
            $credentialId,
            $name,
            $externalSessionId,
            $archivedAt,
            $archiveReason,
            $workspace,
            $createdAt,
            $updatedAt,
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getExternalSessionId(): ?string
    {
        return $this->externalSessionId;
    }

    public function getArchivedAt(): ?int
    {
        return $this->archivedAt;
    }

    public function getArchiveReason(): ?string
    {
        return $this->archiveReason;
    }

    public function getWorkspace(): Workspace
    {
        return $this->workspace;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): int
    {
        return $this->updatedAt;
    }

    public function estArhiv(): bool
    {
        return $this->archivedAt !== null;
    }

    public function prinadlezhit(string $accountId): bool
    {
        return $this->accountId === $accountId;
    }

    public function trebovatVladelca(string $accountId): void
    {
        if (!$this->prinadlezhit($accountId)) {
            throw new \DomainException('opencode.workspace.not_found');
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

    public function snovaAktivna(int $now): void
    {
        if (!$this->estArhiv()) {
            return;
        }

        $this->archivedAt = null;
        $this->archiveReason = null;
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
}
