<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\Unit\SozdatUnit;

/**
 * Том, выбранный клиентом при создании юнита.
 *
 * `dir` и `name` клиент получает из ответа `poluchitKonfigIzHranilisha`
 * (схема `UnitVolume`), `fileId` — идентификатор файла, загруженного
 * в медиатеку как черновик. Валидация значений выполняется в домене
 * (`VolumeValue`), чтобы ошибки формата отдавались как `FailEnvelope`.
 */
final class VolumeZapros
{
    public function __construct(
        public readonly string $dir = '',
        public readonly string $name = '',
        public readonly ?string $fileId = null,
    )
    {
    }
}
