<?php

declare(strict_types=1);

namespace App\Runner\Business\Model;

/**
 * Краткоживущий грант раннера на одну git-операцию.
 *
 * Хранить только в памяти на время активности; никогда не сериализовать
 * в Temporal payload, workflow state, bolt-store или логи.
 */
final class RepoCredentialGrant
{
    public function __construct(
        public readonly string $repoId,
        public readonly string $projectId,
        public readonly string $repoUrl,
        public readonly string $username,
        public readonly string $token,
        public readonly int $expiresAt,
    ) {
    }
}
