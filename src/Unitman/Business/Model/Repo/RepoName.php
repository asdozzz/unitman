<?php

namespace App\Unitman\Business\Model\Repo;

final class RepoName implements \Stringable
{
    private string $name;

    public function __construct(string $name)
    {
        if (empty($name)) {
            throw new \DomainException('repo.name_is_empty');
        }

        if (strlen($name) < 3 || strlen($name) > 60) {
            throw new \DomainException('repo.length_name_invalid');
        }

        $this->name = $name;
    }


    public function __toString(): string
    {
        return $this->name;
    }
}
