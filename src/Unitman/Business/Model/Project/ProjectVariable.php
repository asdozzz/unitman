<?php

namespace App\Unitman\Business\Model\Project;

final class ProjectVariable
{
    public readonly string $code;
    public readonly string $value;

    public function __construct(public readonly ProjectVariableType $tip, string $code, string $value)
    {
        self::validate($code, $value);
        $this->code = $code;
        $this->value = $value;
    }

    static function validate(string $code, string $value): void
    {
        if (empty($code)) {
            throw new \DomainException('project.variable.code_is_empty');
        }

        if (empty($value)) {
            throw new \DomainException('project.variable.value_is_empty');
        }
    }
}
