<?php

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnit;
use App\Unitman\Business\Model\Unit;
use App\Unitman\Business\Model\Unit\Event\KonfigUnitaUstanovlen;
use App\Unitman\Business\Model\Unit\Event\PeremenieUnitaZapolneni;
use PHPUnit\Framework\TestCase;

/**
 * Домен обязан принимать оба формата значений переменных:
 *  - публичный список объектов `{id, value, type}` (как в OpenAPI);
 *  - канонический map `id => value`.
 *
 * Регрессия: раньше список воспринимался как map, из-за чего домен падал
 * с `unit.config.type_for_variable_not_found`.
 */
final class ZapolnitPeremenieUnitaTest extends TestCase
{
    private const CONFIG = [
        'variables' => [
            ['id' => 'VITE_APP_TITLE', 'label' => 'VITE_APP_TITLE', 'type' => 'string', 'defaultValue' => 'title'],
            ['id' => 'TEST_LIST', 'label' => 'TEST_LIST', 'type' => 'collection', 'defaultValue' => '1', 'options' => [
                'options' => [
                    ['id' => '1', 'name' => 'one'],
                    ['id' => '2', 'name' => 'two'],
                ],
            ]],
            ['id' => 'COUNT', 'label' => 'COUNT', 'type' => 'integer', 'defaultValue' => '0'],
        ],
        'prepare' => ['ls -la'],
        'reset_prepare' => ['ls -la'],
        'up' => ['ls -la'],
        'down' => ['ls -la'],
        'services' => [
            'web' => [
                'ports' => [
                    ['port' => 8081, 'type' => 'http', 'startUri' => '/test'],
                ],
            ],
        ],
    ];

    /**
     * @test
     */
    public function list_of_objects_from_public_contract_is_accepted(): void
    {
        $unit = $this->unitSKonfigom();

        $unit->zapolnitPeremenie([
            ['id' => 'VITE_APP_TITLE', 'type' => 'string', 'value' => 'http://8089.yyy.exunit-front.localhost:8087/apiSuffix'],
            ['id' => 'TEST_LIST', 'type' => 'collection', 'value' => '2'],
            ['id' => 'COUNT', 'type' => 'integer', 'value' => '10'],
        ]);

        $this->assertSame('http://8089.yyy.exunit-front.localhost:8087/apiSuffix', $this->znacheniya($unit)['VITE_APP_TITLE']->getValue());
        $this->assertSame('2', $this->znacheniya($unit)['TEST_LIST']->getValue());
        $this->assertSame('10', $this->znacheniya($unit)['COUNT']->getValue());

        $event = $this->posledneeZapolnenie($unit);
        $this->assertSame([
            'VITE_APP_TITLE' => 'http://8089.yyy.exunit-front.localhost:8087/apiSuffix',
            'TEST_LIST' => '2',
            'COUNT' => '10',
        ], $event->values);
    }

    /**
     * @test
     */
    public function canonical_map_is_accepted(): void
    {
        $unit = $this->unitSKonfigom();

        $unit->zapolnitPeremenie([
            'VITE_APP_TITLE' => 'title',
            'TEST_LIST' => '1',
            'COUNT' => '3',
        ]);

        $event = $this->posledneeZapolnenie($unit);
        $this->assertSame([
            'VITE_APP_TITLE' => 'title',
            'TEST_LIST' => '1',
            'COUNT' => '3',
        ], $event->values);
    }

    /**
     * @test
     */
    public function event_stores_map_regardless_of_input_format(): void
    {
        $unit = $this->unitSKonfigom();

        $unit->zapolnitPeremenie([
            ['id' => 'VITE_APP_TITLE', 'type' => 'string', 'value' => 'title'],
            ['id' => 'TEST_LIST', 'type' => 'collection', 'value' => '2'],
            ['id' => 'COUNT', 'type' => 'integer', 'value' => '0'],
        ]);

        $values = $this->posledneeZapolnenie($unit)->values;

        $this->assertArrayHasKey('VITE_APP_TITLE', $values);
        $this->assertArrayNotHasKey(0, $values);
        $this->assertArrayNotHasKey(1, $values);
        $this->assertArrayNotHasKey(2, $values);
    }

    /**
     * @test
     */
    public function zero_value_is_valid_and_not_treated_as_empty(): void
    {
        $unit = $this->unitSKonfigom();

        $unit->zapolnitPeremenie([
            'VITE_APP_TITLE' => 'title',
            'TEST_LIST' => '1',
            'COUNT' => '0',
        ]);

        $this->assertSame('0', $this->znacheniya($unit)['COUNT']->getValue());
    }

    /**
     * @test
     */
    public function missing_variable_is_reported(): void
    {
        $unit = $this->unitSKonfigom();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('value for variable with id=COUNT not defined');

        $unit->zapolnitPeremenie([
            ['id' => 'VITE_APP_TITLE', 'type' => 'string', 'value' => 'title'],
            ['id' => 'TEST_LIST', 'type' => 'collection', 'value' => '2'],
        ]);
    }

    /**
     * @test
     */
    public function variable_absent_in_config_is_reported(): void
    {
        $unit = $this->unitSKonfigom();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.config.type_for_variable_not_found');

        $unit->zapolnitPeremenie([
            'VITE_APP_TITLE' => 'title',
            'TEST_LIST' => '1',
            'COUNT' => '1',
            'UNKNOWN' => 'value',
        ]);
    }

    /**
     * @test
     */
    public function empty_value_is_reported(): void
    {
        $unit = $this->unitSKonfigom();

        // Домен отбрасывает пустое значение ещё на этапе создания VariableValue,
        // до валидации по конфигу.
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.variable_value.value_is_empty');

        $unit->zapolnitPeremenie([
            'VITE_APP_TITLE' => '',
            'TEST_LIST' => '1',
            'COUNT' => '1',
        ]);
    }

    /**
     * @test
     */
    public function config_is_required(): void
    {
        $unit = Unit::sozdatUnit(
            'unit-id',
            'author-id',
            'prozes-id',
            new SozdatUnit('project-id', 'unit-name', 'feature/1', [], 3000)
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.config_unita_ne_opredelen');

        $unit->zapolnitPeremenie(['VITE_APP_TITLE' => 'title']);
    }

    private function unitSKonfigom(): Unit
    {
        $unit = Unit::sozdatUnit(
            'unit-id',
            'author-id',
            'prozes-id',
            new SozdatUnit('project-id', 'unit-name', 'feature/1', [], 3000)
        );

        $unit->applyKonfigUnitaUstanovlen(new KonfigUnitaUstanovlen('unit-id', self::CONFIG));

        return $unit;
    }

    /**
     * @return array<string, \App\Unitman\Business\Model\Unit\VariableValue>
     */
    private function znacheniya(Unit $unit): array
    {
        $result = [];
        foreach ($unit->poluchitZnacheniyaPeremenih() as $variableValue) {
            $result[$variableValue->getId()] = $variableValue;
        }

        return $result;
    }

    private function posledneeZapolnenie(Unit $unit): PeremenieUnitaZapolneni
    {
        $result = null;
        foreach ($unit->releaseEvents() as $event) {
            if ($event instanceof PeremenieUnitaZapolneni) {
                $result = $event;
            }
        }

        $this->assertNotNull($result, 'Ожидалось событие PeremenieUnitaZapolneni');
        /** @var PeremenieUnitaZapolneni $result */
        return $result;
    }
}
