<?php

namespace App\Account\Business\Model\Account;

use EventSauce\EventSourcing\AggregateRootId;

final class AccountId implements AggregateRootId
{
    private string $id;

    public function __construct(string $id)
    {
        if (empty($id)) {
            throw new \Exception('AccountId is empty');
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
