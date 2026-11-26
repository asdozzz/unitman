<?php

declare(strict_types=1);

namespace App\Unitman\Business\ReadModel\Repo;

/**
 * Короткоживущий грант доступа раннера к git-репозиторию.
 *
 * `repoUrl` всегда без userinfo. `token` существует только в памяти раннера
 * на время одной git-операции и не должен сериализоваться в Temporal,
 * логи, state или read-модели.
 */
final class RepoCredentialGrantReadModel
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
