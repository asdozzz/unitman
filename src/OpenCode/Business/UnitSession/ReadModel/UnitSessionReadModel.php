<?php

declare(strict_types=1);

namespace App\OpenCode\Business\UnitSession\ReadModel;

use App\OpenCode\Business\UnitSession\Model\UnitSession;

final class UnitSessionReadModel
{
    public function __construct(
        public readonly string $id,
        public readonly string $credentialId,
        public readonly string $title,
        public readonly ?string $externalSessionId,
        public readonly string $unitId,
        public readonly string $unitProjectId,
        public readonly bool $archived,
        public readonly ?string $archiveReason,
        public readonly int $createdAt,
        public readonly int $updatedAt,
    ) {
    }

    public static function izModela(UnitSession $session): self
    {
        return new self(
            $session->getId(),
            $session->getCredentialId(),
            $session->getTitle(),
            $session->getExternalSessionId(),
            $session->getUnitId(),
            $session->getUnitProjectId(),
            $session->estArhiv(),
            $session->getArchiveReason(),
            $session->getCreatedAt(),
            $session->getUpdatedAt(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'credentialId' => $this->credentialId,
            'title' => $this->title,
            'externalSessionId' => $this->externalSessionId,
            'unitId' => $this->unitId,
            'unitProjectId' => $this->unitProjectId,
            'archived' => $this->archived,
            'archiveReason' => $this->archiveReason,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
