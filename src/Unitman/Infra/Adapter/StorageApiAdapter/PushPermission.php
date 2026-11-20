<?php

declare(strict_types=1);

namespace App\Unitman\Infra\Adapter\StorageApiAdapter;

/**
 * Разбор ответов хостингов о правах на push.
 *
 * Вынесено отдельно, чтобы правила можно было покрыть тестами без сети.
 */
final class PushPermission
{
    /**
     * GitLab: Developer (30) и выше могут пушить.
     */
    public const GITLAB_MIN_ACCESS_LEVEL = 30;

    /**
     * @param array<string, mixed> $project
     */
    public static function izGitlab(array $project): bool
    {
        $projectAccess = $project['permissions']['project_access']['access_level'] ?? null;
        $groupAccess = $project['permissions']['group_access']['access_level'] ?? null;

        return self::dostatochnoDlyaPusha($projectAccess)
            || self::dostatochnoDlyaPusha($groupAccess);
    }

    /**
     * @param array<string, mixed> $repository
     */
    public static function izGithub(array $repository): bool
    {
        return ($repository['permissions']['push'] ?? false) === true;
    }

    private static function dostatochnoDlyaPusha(mixed $accessLevel): bool
    {
        return is_numeric($accessLevel)
            && (int) $accessLevel >= self::GITLAB_MIN_ACCESS_LEVEL;
    }
}
