<?php

namespace App\Unitman\Business\Command\Repo;

use App\Utils\Converter\JsonBodySerializableInterface;

final class AddRepo implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $repoType,
        public readonly string $repoName,
        public readonly string $repoLogin,
        public readonly string $repoPassword,
        public readonly ?string $repoUrl,
    )
    {
    }
}
