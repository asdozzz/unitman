<?php

namespace App\Unitman\Business\Command\Repo;

use App\Utils\Converter\JsonBodySerializableInterface;

final class GetRepoTypeList implements JsonBodySerializableInterface
{
    public function __construct(public readonly int $limit = 100, public readonly int $offset = 0)
    {
    }
}
