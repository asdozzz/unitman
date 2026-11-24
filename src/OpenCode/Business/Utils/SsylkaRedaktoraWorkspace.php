<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

/**
 * Ссылка на редактор workspace (VS Code в браузере).
 *
 * Формула: `{scheme}://{idePort}.{slug}.workspaces.{host}[:port]`.
 * `publicAddress` — публичный адрес раннера, к которому привязан workspace
 * (`workspace_runner`). Default/fallback host запрещён: если адрес пуст,
 * ссылка не строится — контейнер при этом продолжает работать.
 */
final class SsylkaRedaktoraWorkspace
{
    public static function postroit(string $workspaceSlug, ?string $publicAddress, int $idePort): ?string
    {
        if ($publicAddress === null || trim($publicAddress) === '') {
            return null;
        }

        $parts = parse_url(trim($publicAddress));

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
