<?php

declare(strict_types=1);

namespace App\Skill\Business\Utils;

/**
 * Валидация пользовательского навыка OpenCode.
 *
 * Навыки — это конфигурация, которую пишут пользователи. Проверки держим
 * в одном месте, чтобы модель, репозиторий и будущие UseCase не расходились
 * в правилах. Все сообщения — стабильные ключи ошибок вида `skill.*`.
 *
 * Ограничения:
 *  - slug: `^[a-z0-9][a-z0-9-]{0,63}$`;
 *  - name: не длиннее 128 символов;
 *  - description: не длиннее 512 символов;
 *  - content: не длиннее 32 KiB (32768 байт), валидный UTF-8 без NUL;
 *  - проектный навык обязан иметь хотя бы один проект.
 *
 * `content_hash = sha256(name|description|content)`. Разделитель `|` выбран
 * по спецификации фичи. Значения полей не нормализуются: домен валидирует
 * ровно то, что пришло, а нормализацию входа делают контроллеры/UseCase.
 */
final class ProverkaNavyka
{
    public const SLUG_PATTERN = '/\A[a-z0-9][a-z0-9-]{0,63}\z/';
    public const MAX_NAME_LENGTH = 128;
    public const MAX_DESCRIPTION_LENGTH = 512;
    public const MAX_CONTENT_BYTES = 32768;
    public const MAX_ID_LENGTH = 64;
    public const MAX_PROJECT_ID_LENGTH = 64;

    public static function proveritId(string $id): void
    {
        if ($id === '') {
            throw new \DomainException('skill.id.empty');
        }

        if (\strlen($id) > self::MAX_ID_LENGTH) {
            throw new \DomainException('skill.id.too_long');
        }
    }

    public static function proveritSlug(string $slug): void
    {
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            throw new \DomainException('skill.slug.invalid');
        }
    }

    public static function proveritNazvanie(string $name): void
    {
        self::proveritUtf8BezNul($name, 'skill.name.invalid_utf8', 'skill.name.contains_nul');

        if (self::dlinaStroki($name) > self::MAX_NAME_LENGTH) {
            throw new \DomainException('skill.name.too_long');
        }
    }

    public static function proveritOpisanie(string $description): void
    {
        self::proveritUtf8BezNul($description, 'skill.description.invalid_utf8', 'skill.description.contains_nul');

        if (self::dlinaStroki($description) > self::MAX_DESCRIPTION_LENGTH) {
            throw new \DomainException('skill.description.too_long');
        }
    }

    public static function proveritSoderzhimoe(string $content): void
    {
        self::proveritUtf8BezNul($content, 'skill.content.invalid_utf8', 'skill.content.contains_nul');

        if (\strlen($content) > self::MAX_CONTENT_BYTES) {
            throw new \DomainException('skill.content.too_long');
        }
    }

    /**
     * @param list<string> $projectIds уже нормализованный список
     */
    public static function proveritOblast(bool $isGlobal, array $projectIds): void
    {
        if (!$isGlobal && $projectIds === []) {
            throw new \DomainException('skill.project.required');
        }
    }

    /**
     * Trim, отбрасывание пустых и дедупликация с сохранением порядка.
     *
     * @param list<string> $projectIds
     *
     * @return list<string>
     */
    public static function normalizovatProekty(array $projectIds): array
    {
        $rezultat = [];

        foreach ($projectIds as $projectId) {
            $projectId = trim($projectId);

            if ($projectId === '') {
                throw new \DomainException('skill.project.invalid');
            }

            if (\strlen($projectId) > self::MAX_PROJECT_ID_LENGTH) {
                throw new \DomainException('skill.project.too_long');
            }

            if (!\in_array($projectId, $rezultat, true)) {
                $rezultat[] = $projectId;
            }
        }

        return $rezultat;
    }

    public static function hashKontenta(string $name, string $description, string $content): string
    {
        return hash('sha256', $name . '|' . $description . '|' . $content);
    }

    private static function proveritUtf8BezNul(string $value, string $utf8Key, string $nulKey): void
    {
        // `preg_match('//u', ...)` не требует ext-mbstring и возвращает 1
        // только для валидной UTF-8-последовательности.
        if (preg_match('//u', $value) !== 1) {
            throw new \DomainException($utf8Key);
        }

        if (str_contains($value, "\0")) {
            throw new \DomainException($nulKey);
        }
    }

    private static function dlinaStroki(string $value): int
    {
        // В проде mbstring есть, но composer его не требует. Фолбэк на strlen
        // сохраняет работоспособность и не ломает граничные проверки.
        return \function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : \strlen($value);
    }
}
