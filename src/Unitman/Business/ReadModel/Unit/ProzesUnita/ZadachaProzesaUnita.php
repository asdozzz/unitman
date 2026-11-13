<?php

namespace App\Unitman\Business\ReadModel\Unit\ProzesUnita;

use App\Unitman\Business\Model\Unit\Runner\RunnerJobState;

final class ZadachaProzesaUnita
{
    /**
     * @param list<ShagZadachiUnita> $steps
     * */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $state,
        public readonly array $steps = []
    )
    {
    }

    /**
     * Задача в терминальном состоянии: SUCCESS, ERROR или CANCLED.
     *
     * Регистр нормализуется, так как read-модель может прийти как из
     * проекции (значения enum в верхнем регистре), так и из тестовых
     * данных/старых записей в нижнем.
     */
    public function isFinished(): bool
    {
        return in_array(
            strtoupper($this->state),
            [RunnerJobState::SUCCESS->value, RunnerJobState::ERROR->value, RunnerJobState::CANCLED->value],
            true,
        );
    }

    /**
     * Задача завершена успешно (только SUCCESS, отмена и ошибка — false).
     */
    public function isSuccess(): bool
    {
        return strtoupper($this->state) === RunnerJobState::SUCCESS->value;
    }

    /**
     * Время последнего шага с непустым unixtime; 0, если таких шагов нет.
     *
     * В read-модели нет отдельного поля «время обновления», поэтому для
     * прогресса, собранного из БД, используем unixtime последнего шага.
     */
    public function getLastUnixtime(): int
    {
        $result = 0;
        foreach ($this->steps as $step) {
            if (!empty($step->unixtime)) {
                $result = $step->unixtime;
            }
        }

        return $result;
    }
}
