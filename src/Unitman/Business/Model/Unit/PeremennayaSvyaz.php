<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\Unit;

/**
 * Связь переменной конфига юнита с юнитом, который пользователь выбрал
 * через хелпер переменной (PomoschnikSsylki).
 *
 * Хелпер хранит только projectId/service/port/suffix, поэтому выбранный
 * юнит приходит с клиента и фиксируется отдельно.
 */
final class PeremennayaSvyaz
{
    private string $variableId;
    private string $targetUnitId;

    public function __construct(string $variableId, string $targetUnitId)
    {
        $variableId = trim($variableId);
        $targetUnitId = trim($targetUnitId);

        if ($variableId === '') {
            throw new \DomainException('unit.svyaz.variable_id_is_empty');
        }

        if ($targetUnitId === '') {
            throw new \DomainException('unit.svyaz.target_unit_id_is_empty');
        }

        $this->variableId = $variableId;
        $this->targetUnitId = $targetUnitId;
    }

    static function fromArray(array $data): static
    {
        return new static(
            (string)($data['variableId'] ?? ''),
            (string)($data['targetUnitId'] ?? '')
        );
    }

    public function toArray(): array
    {
        return [
            'variableId' => $this->variableId,
            'targetUnitId' => $this->targetUnitId,
        ];
    }

    public function getVariableId(): string
    {
        return $this->variableId;
    }

    public function getTargetUnitId(): string
    {
        return $this->targetUnitId;
    }
}
