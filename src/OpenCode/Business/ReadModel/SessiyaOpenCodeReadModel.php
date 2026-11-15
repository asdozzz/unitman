<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

use App\OpenCode\Business\Model\PrivyazkaKUnitu;
use App\OpenCode\Business\Model\SessiyaOpenCode;

final class SessiyaOpenCodeReadModel
{
    /**
     * `unitId`/`unitProjectId` продублированы ради обратной совместимости: это первый
     * юнит, то есть рабочий каталог хода. Полный упорядоченный список — в `units`.
     *
     * @param list<PrivyazkaKUnituReadModel> $units
     */
    public function __construct(
        public readonly string $id,
        public readonly string $credentialId,
        public readonly string $title,
        public readonly ?string $externalSessionId,
        public readonly array $units,
        public readonly ?string $unitId,
        public readonly ?string $unitProjectId,
        public readonly bool $archived,
        public readonly ?string $archiveReason,
        public readonly int $createdAt,
        public readonly int $updatedAt,
    ) {
    }

    public static function izModela(SessiyaOpenCode $session): self
    {
        return new self(
            $session->getId(),
            $session->getCredentialId(),
            $session->getTitle(),
            $session->getExternalSessionId(),
            array_map(
                static fn (PrivyazkaKUnitu $privyazka): PrivyazkaKUnituReadModel => PrivyazkaKUnituReadModel::izModela($privyazka),
                $session->getUnits(),
            ),
            $session->getUnitId(),
            $session->getUnitProjectId(),
            $session->estArhiv(),
            $session->getArchiveReason(),
            $session->getCreatedAt(),
            $session->getUpdatedAt(),
        );
    }
}
