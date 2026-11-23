<?php

declare(strict_types=1);

namespace App\Unitman\Business\Command\Unit;

use App\Unitman\Business\Command\Unit\SozdatUnitiDlyaProektov\ProektDlyaSozdaniyaUnita;
use App\Utils\Converter\JsonBodySerializableInterface;

/**
 * Групповое создание юнитов для набора проектов с автоматическими связями
 * через хелперы переменных.
 *
 * Имя юнита общее для всех проектов: в пределах одного проекта оно обязано
 * быть уникальным, между разными проектами конфликта нет.
 */
final class SozdatUnitiDlyaProektov implements JsonBodySerializableInterface
{
    /**
     * @param ProektDlyaSozdaniyaUnita[] $projects
     * @param int|null $memoryLimit null — взять лимит памяти из настроек проекта
     */
    public function __construct(
        public readonly string $unitName,
        public readonly array $projects,
        public readonly ?int $memoryLimit = null,
    ) {
    }
}
