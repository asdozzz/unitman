<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

use App\OpenCode\Business\Model\SessiyaOpenCode;

final class SessiyaOpenCodeReadModel
{
    public function __construct(
        public readonly string $id,
        public readonly string $credentialId,
        public readonly string $title,
        public readonly ?string $externalSessionId,
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
            $session->getCreatedAt(),
            $session->getUpdatedAt(),
        );
    }
}