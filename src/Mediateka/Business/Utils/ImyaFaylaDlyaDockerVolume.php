<?php

declare(strict_types=1);

namespace App\Mediateka\Business\Utils;

use Symfony\Component\String\UnicodeString;

/**
 * Приводит имя загружаемого файла к виду, допустимому для имени docker-тома.
 *
 * Docker принимает имена томов по шаблону `^[a-zA-Z0-9][a-zA-Z0-9_.-]+$`
 * (moby `restrictedNamePattern`): только латиница, цифры, `_`, `.`, `-`,
 * причём первый символ — буква или цифра, а длина — не меньше двух символов.
 * Поэтому имя транслитерируется в ASCII, приводится к нижнему регистру,
 * недопустимые символы (пробелы, кириллица, скобки, разделители пути) заменяются
 * на `-`, а результирующая строка обрезается по {@see self::MAX_LENGTH}.
 *
 * Имя `name` в реестре медиатеки используется и как имя файла для скачивания,
 * и как `FileName` тома при подготовке юнита, поэтому нормализация выполняется
 * один раз при загрузке.
 */
final class ImyaFaylaDlyaDockerVolume
{
    /**
     * Ограничение длины с запасом: docker не задаёт лимит явно,
     * но длинные имена мешают монтированию и обрезаются файловыми системами.
     */
    public const MAX_LENGTH = 128;

    /**
     * Минимальная длина имени по правилам docker (moby: `[a-zA-Z0-9][a-zA-Z0-9_.-]+`).
     */
    public const MIN_LENGTH = 2;

    /**
     * Имя для случая, когда после нормализации ничего не осталось.
     */
    public const DEFAULT_NAME = 'file';

    /**
     * Расширение архива: по нему файл распознаётся в раннере.
     */
    public const ZIP_EXTENSION = '.zip';

    /**
     * Шаблон docker-имени тома (moby: restrictedNamePattern), приведённый к нижнему регистру.
     * Лимиты продублированы литералами: в константном выражении sprintf недоступен.
     */
    private const VALID_PATTERN = '/^[a-z0-9][a-z0-9_.-]{1,127}$/';

    public static function normalizovat(string $imya): string
    {
        $imya = str_replace("\0", '', $imya);
        $imya = self::vAscii($imya);
        $imya = strtolower($imya);

        // Всё, что не входит в разрешённый набор, становится разделителем.
        $imya = (string) preg_replace('/[^a-z0-9._-]+/', '-', $imya);
        $imya = (string) preg_replace('/-{2,}/', '-', $imya);

        // Точки — границы расширения: дефисы рядом с ними лишние.
        $imya = (string) preg_replace('/-*\.-*/', '.', $imya);
        $imya = (string) preg_replace('/\.{2,}/', '.', $imya);

        // Имя тома не может начинаться/заканчиваться на `.`, `-`, `_`.
        $imya = trim($imya, '.-_');

        if ($imya === '') {
            return self::DEFAULT_NAME;
        }

        $imya = self::obrezat($imya);

        // Docker требует минимум два символа: `a` → `a-file`.
        if (strlen($imya) < self::MIN_LENGTH) {
            $imya .= '-file';
        }

        return $imya;
    }

    /**
     * Имя zip-архива медиатеки: нормализованное имя docker-тома
     * с обязательным расширением {@see self::ZIP_EXTENSION}.
     */
    public static function normalizovatImyaArhiva(string $imya): string
    {
        $imya = self::normalizovat($imya);

        if (str_ends_with($imya, self::ZIP_EXTENSION)) {
            return $imya;
        }

        return self::normalizovat($imya . self::ZIP_EXTENSION);
    }

    /**
     * Проверка, что имя уже удовлетворяет правилам docker-тома.
     */
    public static function etoDopustimoeImya(string $imya): bool
    {
        return $imya !== ''
            && strlen($imya) >= self::MIN_LENGTH
            && strlen($imya) <= self::MAX_LENGTH
            && preg_match(self::VALID_PATTERN, $imya) === 1;
    }

    /**
     * Транслитерация в ASCII. UnicodeString::ascii() не требует ext-intl,
     * но бросает исключение на битой UTF-8 — тогда просто вычищаем не-ASCII.
     */
    private static function vAscii(string $imya): string
    {
        if (!mb_check_encoding($imya, 'UTF-8')) {
            return (string) preg_replace('/[^\x20-\x7E]+/', ' ', $imya);
        }

        try {
            return (new UnicodeString($imya))->ascii()->toString();
        } catch (\InvalidArgumentException) {
            return (string) preg_replace('/[^\x20-\x7E]+/', ' ', $imya);
        }
    }

    /**
     * Обрезка с сохранением расширения: расширение важнее «хвоста» имени.
     */
    private static function obrezat(string $imya): string
    {
        if (strlen($imya) <= self::MAX_LENGTH) {
            return $imya;
        }

        $tochka = strrpos($imya, '.');

        $rasshirenie = '';
        $bazovoe = $imya;

        if ($tochka !== false && $tochka > 0) {
            $kandidat = substr($imya, $tochka);
            if (strlen($kandidat) < self::MAX_LENGTH) {
                $rasshirenie = $kandidat;
                $bazovoe = substr($imya, 0, $tochka);
            }
        }

        $bazovoe = rtrim(substr($bazovoe, 0, self::MAX_LENGTH - strlen($rasshirenie)), '.-_');

        return $bazovoe === '' ? '' : $bazovoe . $rasshirenie;
    }
}
