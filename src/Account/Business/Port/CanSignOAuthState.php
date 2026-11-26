<?php

declare(strict_types=1);

namespace App\Account\Business\Port;

interface CanSignOAuthState
{
    /**
     * @param array<string, mixed> $payload
     */
    public function podpisat(array $payload): string;

    /**
     * @return array<string, mixed>
     *
     * @throws \DomainException `oauth.state.invalid`
     */
    public function proverit(string $state): array;
}
