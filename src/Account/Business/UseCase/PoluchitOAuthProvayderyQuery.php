<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase;

use App\Account\Business\Port\OAuthProvayderRegistry;

final class PoluchitOAuthProvayderyQuery
{
    public function __construct(private OAuthProvayderRegistry $providerRegistry)
    {
    }

    /**
     * @return array{providers: list<string>}
     */
    public function handle(): array
    {
        return ['providers' => $this->providerRegistry->nastroennye()];
    }
}
