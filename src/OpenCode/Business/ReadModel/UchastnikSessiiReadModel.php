<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

use App\OpenCode\Business\Model\UchastnikSessii;

/**
 * Участник workspace для интерфейса: роль и личные реквизиты ходов.
 *
 * Секрет реквизитов здесь отсутствует — только идентификатор и модель.
 */
final class UchastnikSessiiReadModel
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $role,
        public readonly bool $vladelec,
        public readonly ?string $defaultCredentialId,
        public readonly ?string $defaultModel,
        public readonly int $createdAt,
        public readonly int $updatedAt,
    ) {
    }

    public static function izModela(UchastnikSessii $uchastnik): self
    {
        return new self(
            $uchastnik->getAccountId(),
            $uchastnik->getRol()->value,
            $uchastnik->estVladelec(),
            $uchastnik->getDefaultCredentialId(),
            $uchastnik->getDefaultModel(),
            $uchastnik->getCreatedAt(),
            $uchastnik->getUpdatedAt(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function vMassiv(): array
    {
        return [
            'accountId' => $this->accountId,
            'role' => $this->role,
            'owner' => $this->vladelec,
            'defaultCredentialId' => $this->defaultCredentialId,
            'defaultModel' => $this->defaultModel,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
