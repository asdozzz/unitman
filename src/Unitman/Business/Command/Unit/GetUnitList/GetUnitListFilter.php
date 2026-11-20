<?php

namespace App\Unitman\Business\Command\Unit\GetUnitList;

final class GetUnitListFilter
{
    /**
     * @param string[] $unitIds вернуть переданные юниты и связанные с ними
     *        (связи через хелпер переменной, в обе стороны)
     */
    public function __construct(
        public readonly bool $onlyMine = false,
        public readonly ?string $name = null,
        public readonly ?string $branch = null,
        public readonly ?string $projectId = null,
        public readonly ?string $unitId = null,
        /** @var string[] */
        public readonly array $unitIds = [],
        /** Подстрока по имени ИЛИ ветке (регистронезависимый поиск). Пустая строка — без фильтра. */
        public readonly ?string $search = null,
        /**
         * Статусы юнитов для фильтрации: `running`, `busy`, `prepared`, `stopped`, `error`.
         * Неизвестные значения игнорируются; пустой массив — без фильтра.
         *
         * @var string[]
         */
        public readonly array $statuses = [],
        /** Сортировка: `created_desc`, `updated_desc`, `name_asc`, `status_priority`. По умолчанию `created_desc`. */
        public readonly string $sort = 'created_desc',
    )
    {
    }

}
