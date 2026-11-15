<?php

declare(strict_types=1);

namespace App\OpenCode\Business\ReadModel;

use App\OpenCode\Business\Model\PrivyazkaKUnitu;

/**
 * Юнит сессии в списке/деталях: порядок элементов совпадает с порядком привязки,
 * поэтому первый элемент — рабочий каталог хода (cwd), остальные — дополнительные
 * корни workspace (multi-root).
 */
final class PrivyazkaKUnituReadModel
{
    public function __construct(
        public readonly string $unitId,
        public readonly string $unitProjectId,
    ) {
    }

    public static function izModela(PrivyazkaKUnitu $privyazka): self
    {
        return new self($privyazka->unitId, $privyazka->unitProjectId);
    }

    /**
     * @return array{unitId: string, unitProjectId: string}
     */
    public function toArray(): array
    {
        return [
            'unitId' => $this->unitId,
            'unitProjectId' => $this->unitProjectId,
        ];
    }
}
