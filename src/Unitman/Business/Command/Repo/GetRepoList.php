<?php

namespace App\Unitman\Business\Command\Repo;

use App\Utils\Converter\JsonBodySerializableInterface;

final class GetRepoList implements JsonBodySerializableInterface
{
    public function __construct(public readonly int $limit = 10, public readonly int $offset = 0)
    {
    }

}
