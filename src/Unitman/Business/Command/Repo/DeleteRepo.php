<?php

namespace App\Unitman\Business\Command\Repo;

use App\Utils\Converter\JsonBodySerializableInterface;

final class DeleteRepo implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $repoId
    )
    {
    }

}
