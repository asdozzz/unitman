<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Model\Unit\Runner\RunnerJobStep;
use PHPUnit\Framework\TestCase;

/**
 * Обратная совместимость шага задачи раннера.
 *
 * Время начала шага (`unixtimeStart`) добавлено после релиза, поэтому:
 *  - события и снапшоты без ключа должны продолжать читаться;
 *  - для шагов без времени начала массив должен совпадать с прежним
 *    (ровно 4 ключа), иначе изменился бы payload API и старые тесты.
 */
final class RunnerJobStepTest extends TestCase
{
    public function test_to_array_bez_vremeni_nachala_daet_rovno_chetyre_klyucha(): void
    {
        $step = new RunnerJobStep('docker build', 'ok', true, 1730000000);

        $this->assertSame(
            [
                'command' => 'docker build',
                'response' => 'ok',
                'success' => true,
                'unixtime' => 1730000000,
            ],
            $step->toArray(),
        );
    }

    public function test_to_array_s_vremenem_nachala_daet_pyat_klyuchei(): void
    {
        $step = new RunnerJobStep('docker build', 'ok', true, 1730000000, 1729999975);

        $this->assertSame(
            [
                'command' => 'docker build',
                'response' => 'ok',
                'success' => true,
                'unixtime' => 1730000000,
                'unixtimeStart' => 1729999975,
            ],
            $step->toArray(),
        );
    }

    public function test_from_array_bez_klyucha_daet_null(): void
    {
        // Шаг из события, сохранённого до появления поля.
        $step = RunnerJobStep::fromArray([
            'command' => 'docker build',
            'response' => 'ok',
            'success' => true,
            'unixtime' => 1730000000,
        ]);

        $this->assertNull($step->unixtimeStart);
        $this->assertArrayNotHasKey('unixtimeStart', $step->toArray());
    }

    public function test_from_array_s_klyuchom_sohranyaet_vremya_nachala(): void
    {
        $step = RunnerJobStep::fromArray([
            'command' => 'docker build',
            'response' => 'ok',
            'success' => true,
            'unixtime' => 1730000000,
            'unixtimeStart' => 1729999975,
        ]);

        $this->assertSame(1729999975, $step->unixtimeStart);
    }

    public function test_krugovaya_serializaciya_s_vremenem_nachala_stabilna(): void
    {
        $step = new RunnerJobStep('docker build', 'ok', true, 1730000000, 1729999975);

        $restored = RunnerJobStep::fromArray($step->toArray());

        $this->assertSame($step->toArray(), $restored->toArray());
    }

    public function test_nekorrektnoe_znachenie_vremeni_nachala_pri_chtenii_otbrasyvaetsya(): void
    {
        // Защита от мусора в событиях: поле не должно ронять восстановление шага.
        $step = RunnerJobStep::fromArray([
            'command' => 'docker build',
            'response' => 'ok',
            'success' => true,
            'unixtime' => 1730000000,
            'unixtimeStart' => 'not-a-timestamp',
        ]);

        $this->assertNull($step->unixtimeStart);
        $this->assertArrayNotHasKey('unixtimeStart', $step->toArray());
    }
}
