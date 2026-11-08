<?php

namespace App\Unitman\Business\Command\Unit\SozdatUnit;

final class ZnacheniePeremenoi
{
    /**
     * @param string|null $targetUnitId юнит, выбранный пользователем через хелпер переменной.
     *                                `null` — хелпер не использовался. Для переменных без хелпера
     *                                значение игнорируется.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $value,
        public readonly string $type,
        public readonly ?string $targetUnitId = null,
    )
    {
    }

}
