<?php

namespace App\Unitman\Business\Model\Project;

final class ProjectCode implements \Stringable
{
    private string $code;

    public function __construct(string $code)
    {
        if (empty($code)) {
            throw new \DomainException('project.code_is_empty');
        }

        $this->code = $code;
    }


    public function __toString(): string
    {
        return $this->code;
    }
}
