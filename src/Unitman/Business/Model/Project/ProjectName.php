<?php

namespace App\Unitman\Business\Model\Project;

final class ProjectName implements \Stringable
{
    private string $name;

    public function __construct(string $name)
    {
        if (empty($name)) {
            throw new \DomainException('project.name_is_empty');
        }

        if (strlen($name) < 3 || strlen($name) > 60) {
            throw new \DomainException('project.length_name_invalid');
        }

        if (!preg_match('/[a-zA-Z0-9_]+/mu', $name)) {
            throw new \DomainException('project.name_invalid');
        }

        $this->name = $name;
    }


    public function __toString(): string
    {
        return $this->name;
    }
}
