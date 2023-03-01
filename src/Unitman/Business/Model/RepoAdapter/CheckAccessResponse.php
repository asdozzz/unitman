<?php

namespace App\Unitman\Business\Model\RepoAdapter;

final class CheckAccessResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message
    )
    {
    }

    static function fromError(string $error): static
    {
        return new static(false, $error);
    }

    static function success(): static
    {
        return new static(true, '');
    }
}
