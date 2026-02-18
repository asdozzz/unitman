<?php

namespace App\Unitman\Business\Command\Repo;

use App\Utils\Converter\JsonBodySerializableInterface;

readonly class PoluchitSpisokProektovRepi implements JsonBodySerializableInterface
{
    public function __construct(public string $id, public ?string $query)
    {
    }

}
