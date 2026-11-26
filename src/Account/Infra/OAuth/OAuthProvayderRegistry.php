<?php

declare(strict_types=1);

namespace App\Account\Infra\OAuth;

use App\Account\Business\Port\OAuthProvayder;
use App\Account\Business\Port\OAuthProvayderRegistry as OAuthProvayderRegistryPort;

final class OAuthProvayderRegistry implements OAuthProvayderRegistryPort
{
    /**
     * @param iterable<OAuthProvayder> $providers
     */
    public function __construct(private readonly iterable $providers)
    {
    }

    public function poluchit(string $provider): OAuthProvayder
    {
        foreach ($this->providers as $provayder) {
            if ($provayder->imya() === $provider && $provayder->nastroen()) {
                return $provayder;
            }
        }

        throw new \DomainException('oauth.provider.not_configured');
    }

    public function nastroennye(): array
    {
        $result = [];

        foreach ($this->providers as $provayder) {
            if ($provayder->nastroen()) {
                $result[] = $provayder->imya();
            }
        }

        return $result;
    }
}
