<?php

namespace App\Unitman\Business\Command;

use App\Utils\Converter\JsonBodySerializableInterface;

final class ChangeCredentialsOfRepo implements JsonBodySerializableInterface
{
    public function __construct(
        public readonly string $repoId,
        public readonly string $repoUrl,
        public readonly string $repoLogin,
        public readonly string $repoPassword
    )
    {
    }

}
