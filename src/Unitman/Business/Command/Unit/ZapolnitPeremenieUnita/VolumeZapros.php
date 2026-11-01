<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\Unit\ZapolnitPeremenieUnita;

/**
 * Том, который клиент заполняет/перезаполняет при вызове `zapolnitPeremenie`.
 *
 * `dir` — уникальный ключ тома из `unitman_volumes` (обязан присутствовать
 * в конфиге юнита). `fileId` — ид архива, загруженного в медиатеку:
 *  - поле отсутствует (null) — оставить текущий файл тома без изменений;
 *  - `fileId: null` — очистить файл тома.
 *
 * Имя тома всегда берётся из конфига, клиент его не передаёт.
 */
final class VolumeZapros
{
    public function __construct(
        public readonly string $dir = '',
        public readonly ?string $fileId = null,
    )
    {
    }
}
