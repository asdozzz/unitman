<?php

declare(strict_types=1);

namespace App\Skill\Business\Utils;

/**
 * Имя каталога-контейнера навыка.
 *
 * Имя обязано быть одним безопасным сегментом пути: без `/`, `\`, `..`,
 * пробелов и управляющих символов. Slug уже соответствует
 * `^[a-z0-9][a-z0-9-]{0,63}$`, а идентификатор проекта может быть
 * произвольным, поэтому для проектного префикса он очищается.
 */
final class ImyaKonteyneraNavyka
{
    public static function dlyaGlobalnogo(string $slug): string
    {
        return 'global-' . $slug;
    }

    public static function dlyaProektnogo(string $projectId, string $slug): string
    {
        return 'p' . self::prefiksProekta($projectId) . '-' . $slug;
    }

    /**
     * Первые 8 безопасных символов идентификатора проекта.
     */
    public static function prefiksProekta(string $projectId): string
    {
        $bezopasniy = strtolower($projectId);
        $bezopasniy = preg_replace('/[^a-z0-9-]+/', '-', $bezopasniy) ?? '';
        $bezopasniy = trim($bezopasniy, '-');

        if ($bezopasniy === '') {
            $bezopasniy = 'project';
        }

        return substr($bezopasniy, 0, 8);
    }

    /**
     * Является ли строка безопасным одиночным сегментом пути.
     */
    public static function etoBezopasnyySegment(string $containerName): bool
    {
        return $containerName !== ''
            && $containerName !== '.'
            && $containerName !== '..'
            && preg_match('/\A[A-Za-z0-9._-]+\z/', $containerName) === 1;
    }
}
