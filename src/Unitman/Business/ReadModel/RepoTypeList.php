<?php

namespace App\Unitman\Business\ReadModel;

final class RepoTypeList
{
    public function __construct(
        public readonly string $code,
        public readonly string $name
    )
    {
    }
}
