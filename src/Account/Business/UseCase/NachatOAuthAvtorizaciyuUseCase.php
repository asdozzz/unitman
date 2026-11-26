<?php

declare(strict_types=1);

namespace App\Account\Business\UseCase;

use App\Account\Business\Port\CanSignOAuthState;
use App\Account\Business\Port\OAuthProvayderRegistry;
use App\Utils\Model\Locale\SupportedLocale;

final class NachatOAuthAvtorizaciyuUseCase
{
    public function __construct(
        private OAuthProvayderRegistry $providerRegistry,
        private CanSignOAuthState $stateCodec,
        private int $oauthStateTtl,
    ) {
    }

    public function handle(string $provider, string $locale = 'ru'): string
    {
        $provayder = $this->providerRegistry->poluchit($provider);
        $codeVerifier = bin2hex(random_bytes(32));

        $state = $this->stateCodec->podpisat([
            'provider' => $provider,
            'exp' => time() + $this->oauthStateTtl,
            'codeVerifier' => $codeVerifier,
            'locale' => SupportedLocale::normalize($locale),
            'nonce' => bin2hex(random_bytes(16)),
        ]);

        return $provayder->ssylkaAvtorizacii($state, $codeVerifier);
    }
}
