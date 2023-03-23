<?php

namespace App\Unitman\Business\Command\Repo;

use App\Utils\Converter\JsonBodySerializableInterface;

final class ChangeCredentialsOfRepo implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $repoId,
        public readonly string $repoLogin,
        public readonly string $repoPassword,
        public readonly ?string $repoUrl = null
    )
    {
    }

}
