<?php

declare(strict_types=1);

namespace App\Unitman\Business\Model\LichniyToken;

use EventSauce\EventSourcing\AggregateRootId;

final class LichniyTokenId implements AggregateRootId
{
    private string $id;

    public function __construct(string $id)
    {
        if ($id === '') {
            throw new \DomainException('lichniy_token.id_is_empty');
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
