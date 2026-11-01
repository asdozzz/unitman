<?php

namespace App\Unitman\Business\Model\Unit;

/**
 * Приводит значения переменных юнита к каноническому виду — map `id => value`.
 *
 * Публичный HTTP-контракт (`ZnacheniePeremenoi`) передаёт список объектов
 * `{id, value, type}`, а домен, событие `PeremenieUnitaZapolneni` и проекции
 * работают с map `id => value`. Тип переменной берётся из конфига юнита,
 * поэтому поле `type` из запроса игнорируется.
 */
final class VariableValuesNormalizer
{
    /**
     * @param array<array-key, mixed> $values
     * @return array<string, string>
     */
    public static function toMap(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $id = (string) $key;
            $rawValue = $value;

            if (is_array($value) || is_object($value)) {
                $item = is_object($value) ? get_object_vars($value) : $value;

                if (!array_key_exists('id', $item) || !array_key_exists('value', $item)) {
                    throw new \DomainException('unit.config.values_format_invalid');
                }

                $id = (string) $item['id'];
                $rawValue = $item['value'];
            }

            if ($id === '') {
                throw new \DomainException('unit.variable_value.id_is_empty');
            }

            if (is_array($rawValue) || is_object($rawValue) || $rawValue === null) {
                throw new \DomainException('unit.config.values_format_invalid');
            }

            if (is_bool($rawValue)) {
                $result[$id] = $rawValue ? '1' : '0';
                continue;
            }

            $result[$id] = (string) $rawValue;
        }

        return $result;
    }
}
