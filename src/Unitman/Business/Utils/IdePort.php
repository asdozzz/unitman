<?php

declare(strict_types=1);

namespace App\Unitman\Business\Utils;

/**
 * Порт read-only редактора файлов юнита.
 *
 * Порт один для всех юнитов (`UNITMAN_IDE_PORT`), потому что редакторы
 * различаются поддоменом, а не внешним портом. Значение резервируется:
 * конфиг юнита не должен занимать его в `services.*.ports`.
 */
final class IdePort
{
    public const ENV_NAME = 'UNITMAN_IDE_PORT';

    public const DEFAULT_PORT = 47000;

    /**
     * Порт из окружения; некорректное или отсутствующее значение заменяется
     * значением по умолчанию, чтобы ссылка всегда была предсказуемой.
     */
    public static function izOkruzheniya(): int
    {
        $raw = $_ENV[self::ENV_NAME] ?? $_SERVER[self::ENV_NAME] ?? getenv(self::ENV_NAME);

        if ($raw === false || $raw === '') {
            return self::DEFAULT_PORT;
        }

        $port = filter_var($raw, FILTER_VALIDATE_INT);

        if ($port === false || $port < 1 || $port > 65535) {
            return self::DEFAULT_PORT;
        }

        return $port;
    }

    /**
     * Порт зарезервирован за редактором и не может использоваться сервисами юнита.
     */
    public static function yavlyaetsyaZarezervirovannim(int $port): bool
    {
        return $port === self::izOkruzheniya();
    }
}
