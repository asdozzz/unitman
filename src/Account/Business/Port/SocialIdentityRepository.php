<?php

declare(strict_types=1);

namespace App\Account\Business\Port;

use App\Account\Business\Model\OAuth\SocialIdentity;

interface SocialIdentityRepository
{
    public function findByProviderAndUserId(string $provider, string $providerUserId): ?SocialIdentity;

    public function findByAccountIdAndProvider(string $accountId, string $provider): ?SocialIdentity;

    public function save(SocialIdentity $identity): void;
}
