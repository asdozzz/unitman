<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase;

use App\Account\Business\Port\CanSignOAuthState;
use App\Account\Business\Port\OAuthProvayderRegistry;

final class ZavershitOAuthAvtorizaciyuUseCase
{
    public function __construct(
        private OAuthProvayderRegistry $providerRegistry,
        private CanSignOAuthState $stateCodec,
        private AuthenticateWithOAuthUseCase $authenticateWithOAuthUseCase,
        private IssueOAuthExchangeCodeUseCase $issueOAuthExchangeCodeUseCase,
        private string $oauthFrontendUrl,
    ) {
    }

    public function handle(string $provider, string $code, string $state): string
    {
        $payload = $this->stateCodec->proverit($state);

        if (($payload['provider'] ?? null) !== $provider) {
            throw new \DomainException('oauth.state.invalid');
        }

        $provayder = $this->providerRegistry->poluchit($provider);
        $oauthUser = $provayder->poluchitPolzovatelya(
            $code,
            (string) ($payload['codeVerifier'] ?? ''),
        );

        $accountId = $this->authenticateWithOAuthUseCase->handle(
            $provider,
            $oauthUser,
            (string) ($payload['locale'] ?? 'ru'),
        );

        $exchangeCode = $this->issueOAuthExchangeCodeUseCase->handle($accountId);

        return rtrim($this->oauthFrontendUrl, '/')
            . '/oauth/callback?code=' . urlencode($exchangeCode);
    }
}
