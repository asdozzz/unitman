<?php

declare(strict_types=1);

namespace App\Account\Business\Port;

interface OAuthProvayderRegistry
{
    /**
     * @throws \DomainException `oauth.provider.not_configured`
     */
    public function poluchit(string $provider): OAuthProvayder;

    /**
     * @return list<string>
     */
    public function nastroennye(): array;
}
