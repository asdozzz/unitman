<?php

namespace App\Runner\Business\Model\GolangRunner\Unit;

final class Step
{
    /**
     * `Unixtime` — время окончания шага, `UnixtimeStart` — необязательное время
     * начала. Поле добавлено последним с дефолтом `null`, чтобы старые ответы
     * активити (без ключа) десериализовались без ошибок.
     */
    public function __construct(
        public readonly string $Command,
        public readonly string $Response,
        public readonly bool $Success,
        public readonly int $Unixtime,
        public readonly ?int $UnixtimeStart = null,
    )
    {
    }
}
