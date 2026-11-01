<?php

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Model\Unit\VariableValuesNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Регрессия на баг `unit.config.type_for_variable_not_found`: публичный контракт
 * передаёт значения переменных списком объектов `{id, value, type}`, а домен
 * ожидает map `id => value`. Нормализатор должен приводить обе формы к map.
 */
final class VariableValuesNormalizerTest extends TestCase
{
    public function test_list_of_objects_is_converted_to_map(): void
    {
        $result = VariableValuesNormalizer::toMap([
            ['id' => 'VITE_APP_TITLE', 'type' => 'string', 'value' => 'http://localhost'],
            ['id' => 'TEST_LIST', 'type' => 'collection', 'value' => '2'],
        ]);

        $this->assertSame([
            'VITE_APP_TITLE' => 'http://localhost',
            'TEST_LIST' => '2',
        ], $result);
    }

    public function test_canonical_map_is_passed_through(): void
    {
        $result = VariableValuesNormalizer::toMap([
            'VITE_APP_TITLE' => 'http://localhost',
            'TEST_LIST' => '2',
        ]);

        $this->assertSame([
            'VITE_APP_TITLE' => 'http://localhost',
            'TEST_LIST' => '2',
        ], $result);
    }

    public function test_objects_are_supported_as_items(): void
    {
        $first = new \stdClass();
        $first->id = 'A';
        $first->value = 'one';

        $second = new \stdClass();
        $second->id = 'B';
        $second->value = 'two';

        $this->assertSame(
            ['A' => 'one', 'B' => 'two'],
            VariableValuesNormalizer::toMap([$first, $second])
        );
    }

    public function test_type_from_request_is_ignored_and_scalars_are_cast_to_string(): void
    {
        $this->assertSame(
            ['INT' => '10', 'FLOAT' => '10.5', 'BOOL_TRUE' => '1', 'BOOL_FALSE' => '0'],
            VariableValuesNormalizer::toMap([
                ['id' => 'INT', 'type' => 'string', 'value' => 10],
                ['id' => 'FLOAT', 'type' => 'string', 'value' => 10.5],
                ['id' => 'BOOL_TRUE', 'type' => 'string', 'value' => true],
                ['id' => 'BOOL_FALSE', 'type' => 'string', 'value' => false],
            ])
        );
    }

    public function test_item_without_value_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.config.values_format_invalid');

        VariableValuesNormalizer::toMap([
            ['id' => 'VITE_APP_TITLE', 'type' => 'string'],
        ]);
    }

    public function test_item_without_id_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.config.values_format_invalid');

        VariableValuesNormalizer::toMap([
            ['value' => '1'],
        ]);
    }

    public function test_nested_array_value_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.config.values_format_invalid');

        VariableValuesNormalizer::toMap([
            'TEST_LIST' => ['1', '2'],
        ]);
    }

    public function test_null_value_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.config.values_format_invalid');

        VariableValuesNormalizer::toMap([
            'TEST_LIST' => null,
        ]);
    }

    public function test_empty_id_is_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.variable_value.id_is_empty');

        VariableValuesNormalizer::toMap([
            '' => 'value',
        ]);
    }
}
