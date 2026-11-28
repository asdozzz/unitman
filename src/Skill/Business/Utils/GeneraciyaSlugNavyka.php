<?php

declare(strict_types=1);

namespace App\Skill\Business\Utils;

/**
 * Генерация slug навыка из человекочитаемого названия.
 *
 * Правило совпадает с валидатором `ProverkaNavyka::SLUG_PATTERN`: результатом
 * является строка из `[a-z0-9-]`, начинающаяся с буквы или цифры, длиной
 * не более 64 символов. Уникальность (для глобальных навыков) обеспечивает
 * вызывающий UseCase.
 */
final class GeneraciyaSlugNavyka
{
    public const MAX_LENGTH = 64;

    public static function izNazvaniya(string $name): string
    {
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        $osnova = $translit === false ? $name : $translit;

        $slug = strtolower($osnova);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        $slug = substr($slug, 0, self::MAX_LENGTH);
        $slug = rtrim($slug, '-');

        return $slug === '' ? 'skill' : $slug;
    }

    /**
     * Детерминированный суффикс для разрешения конфликта slug.
     */
    public static function sSufiksom(string $base, int $nomer): string
    {
        $sufiks = '-' . $nomer;
        $maksimalnayaBaza = self::MAX_LENGTH - strlen($sufiks);

        return rtrim(substr($base, 0, $maksimalnayaBaza), '-') . $sufiks;
    }
}
