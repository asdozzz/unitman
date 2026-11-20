<?php

declare(strict_types=1);

namespace App\OpenCode\Business\Utils;

/**
 * Детерминированный slug workspace.
 *
 * Используется и в команде запуска (`WorkspaceSlug`), и в ссылке на редактор:
 * контейнер называется `${UNITMAN_WORKSPACE_SLUG}.workspaces`, поэтому
 * значения обязаны совпадать. Суффикс из первых 8 символов id исключает
 * коллизии одинаковых человекочитаемых имён.
 */
final class SlugWorkspace
{
    private const MAX_LENGTH = 48;

    private const SUFFIX_LENGTH = 8;

    public static function dlya(string $name, string $id): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        $suffix = self::suffix($id);
        $baseLimit = self::MAX_LENGTH - ($suffix === '' ? 0 : strlen($suffix) + 1);
        $baseLimit = max(1, $baseLimit);

        if ($slug === '') {
            $slug = 'workspace';
        }

        $slug = rtrim(substr($slug, 0, $baseLimit), '-');

        if ($slug === '') {
            $slug = 'workspace';
        }

        return $suffix === '' ? $slug : $slug . '-' . $suffix;
    }

    private static function suffix(string $id): string
    {
        $candidate = substr(strtolower(trim($id)), 0, self::SUFFIX_LENGTH);

        return preg_replace('/[^a-z0-9]/', '', $candidate) ?? '';
    }
}
