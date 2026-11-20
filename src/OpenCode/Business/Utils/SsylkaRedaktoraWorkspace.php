<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

/**
 * Ссылка на редактор workspace (VS Code в браузере).
 *
 * Формула: `{scheme}://{idePort}.{slug}.workspaces.{host}[:port]`.
 * `proxyHost` берётся у первого проекта; пустой proxy host означает, что
 * ссылку построить нельзя — контейнер при этом продолжает работать.
 */
final class SsylkaRedaktoraWorkspace
{
    public static function postroit(string $workspaceSlug, ?string $proxyHost, int $idePort): ?string
    {
        if ($proxyHost === null || trim($proxyHost) === '') {
            return null;
        }

        $parts = parse_url(trim($proxyHost));

        if ($parts === false || !isset($parts['host']) || $parts['host'] === '') {
            return null;
        }

        $scheme = isset($parts['scheme']) && $parts['scheme'] !== '' ? $parts['scheme'] : 'http';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        return sprintf(
            '%s://%d.%s.workspaces.%s%s',
            $scheme,
            max(1, $idePort),
            $workspaceSlug,
            $parts['host'],
            $port,
        );
    }
}
