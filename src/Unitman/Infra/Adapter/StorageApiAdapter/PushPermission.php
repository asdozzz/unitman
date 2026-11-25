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

    /**
     * Gitea, Forgejo и Codeberg отдают права текущего пользователя в
     * permissions.push, как и GitHub.
     *
     * @param array<string, mixed> $repository
     */
    public static function izGitea(array $repository): bool
    {
        return ($repository['permissions']['push'] ?? false) === true;
    }

    /**
     * GitFlic может отдавать права как permissions.push, как числовой
     * accessLevel, либо как роль пользователя в проекте.
     *
     * @param array<string, mixed> $project
     */
    public static function izGitflic(array $project): bool
    {
        if (($project['permissions']['push'] ?? false) === true) {
            return true;
        }

        $accessLevel = $project['accessLevel'] ?? $project['access_level'] ?? null;
        if (is_numeric($accessLevel) && (int)$accessLevel >= self::GITLAB_MIN_ACCESS_LEVEL) {
            return true;
        }

        $role = strtoupper((string)($project['role'] ?? $project['userRole'] ?? ''));

        return in_array($role, ['DEVELOPER', 'MAINTAINER', 'OWNER', 'ADMIN', 'ADMINISTRATOR'], true);
    }

    /**
     * GitVerse отдаёт права пользователя в объекте репозитория. Точное поле
     * зависит от версии API, поэтому поддерживаются permissions.push, роль и
     * числовой уровень доступа.
     *
     * @param array<string, mixed> $repository
     */
    public static function izGitverse(array $repository): bool
    {
        if (($repository['permissions']['push'] ?? false) === true) {
            return true;
        }

        $accessLevel = $repository['accessLevel'] ?? $repository['access_level'] ?? null;
        if (is_numeric($accessLevel) && (int)$accessLevel >= self::GITLAB_MIN_ACCESS_LEVEL) {
            return true;
        }

        $role = strtoupper((string)(
            $repository['role']
            ?? $repository['permission']
            ?? $repository['userRole']
            ?? ''
        ));

        return in_array($role, ['WRITE', 'DEVELOPER', 'MAINTAINER', 'OWNER', 'ADMIN'], true);
    }

    /**
     * Bitbucket Cloud отдаёт permission (read/write/admin) либо роль
     * пользователя в репозитории.
     *
     * @param array<string, mixed> $repository
     */
    public static function izBitbucket(array $repository): bool
    {
        if (($repository['permissions']['push'] ?? false) === true) {
            return true;
        }

        $permission = strtolower((string)($repository['permission'] ?? ''));
        if (in_array($permission, ['write', 'admin'], true)) {
            return true;
        }

        $role = strtoupper((string)($repository['role'] ?? ''));

        return in_array($role, ['WRITE', 'ADMIN', 'OWNER', 'MAINTAINER'], true);
    }

    private static function dostatochnoDlyaPusha(mixed $accessLevel): bool
    {
        return is_numeric($accessLevel)
            && (int) $accessLevel >= self::GITLAB_MIN_ACCESS_LEVEL;
    }
}
