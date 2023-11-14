<?php

namespace App\Unitman\Business\Model\Repo;

use EventSauce\EventSourcing\AggregateRootId;

final class RepoId implements AggregateRootId
{
    private string $id;

    public function __construct(string $id)
    {
        if (empty($id)) {
            throw new \DomainException('repo.id_is_empty');
        }
        $this->id = $id;
    }

    public function toString(): string
    {
        return $this->id;
    }

    public static function fromString(string $aggregateRootId): static
    {
        return new static($aggregateRootId);
    }
}
