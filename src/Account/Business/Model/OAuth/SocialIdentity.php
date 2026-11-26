<?php

declare(strict_types=1);

namespace App\Account\Business\Model\OAuth;

final class SocialIdentity
{
    public function __construct(
        public readonly string $id,
        public readonly string $accountId,
        public readonly string $provider,
        public readonly string $providerUserId,
        public readonly string $email,
        public readonly int $linkedAt,
    ) {
    }
}
