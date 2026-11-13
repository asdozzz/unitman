<?php

namespace App\Unitman\Business\ReadModel\Unit\ProzesUnita;

final class ShagZadachiUnita
{
    /**
     * `$unixtime` — время окончания шага, `$unixtimeStart` — необязательное время
     * начала. Параметр добавлен последним с дефолтом `null`, чтобы шаги,
     * сохранённые в проекции до его появления, не ломали гидратацию.
     */
    public function __construct(
        public readonly string $command,
        public readonly string $response,
        public readonly bool $success,
        public readonly int $unixtime,
        public readonly ?int $unixtimeStart = null)
    {
    }
}
