<?php

declare(strict_types=1);

namespace App\Account\Infra\OAuth;

use App\Account\Business\Port\OAuthProvayder;
use App\Account\Business\ReadModel\OAuthUserReadModel;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GoogleOAuthProvayder implements OAuthProvayder
{
    private const AUTHORIZATION_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $googleClientId,
        private readonly string $googleClientSecret,
        private readonly string $oauthRedirectBaseUrl,
    ) {
    }

    public function imya(): string
    {
        return 'google';
    }

    public function nastroen(): bool
    {
        return trim($this->googleClientId) !== ''
            && trim($this->googleClientSecret) !== ''
            && trim($this->oauthRedirectBaseUrl) !== '';
    }

    public function ssylkaAvtorizacii(string $state, string $codeVerifier): string
    {
        $params = [
            'client_id' => $this->googleClientId,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => $this->base64UrlEncode(hash('sha256', $codeVerifier, true)),
            'code_challenge_method' => 'S256',
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];

        return self::AUTHORIZATION_URL . '?' . http_build_query($params);
    }

    public function poluchitPolzovatelya(string $code, string $codeVerifier): OAuthUserReadModel
    {
        $token = $this->exchangeCode($code, $codeVerifier);

        $data = $this->httpClient->request('GET', self::USERINFO_URL, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
        ])->toArray(false);

        $email = isset($data['email']) ? (string) $data['email'] : '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \DomainException('oauth.provider.error');
        }

        if (($data['email_verified'] ?? false) !== true) {
            throw new \DomainException('oauth.email.not_verified');
        }

        return new OAuthUserReadModel(
            (string) ($data['sub'] ?? $email),
            $email,
            isset($data['name']) ? (string) $data['name'] : null,
        );
    }

    private function exchangeCode(string $code, string $codeVerifier): string
    {
        $data = $this->httpClient->request('POST', self::TOKEN_URL, [
            'headers' => ['Accept' => 'application/json'],
            'body' => [
                'client_id' => $this->googleClientId,
                'client_secret' => $this->googleClientSecret,
                'code' => $code,
                'code_verifier' => $codeVerifier,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri(),
            ],
        ])->toArray(false);

        if (!isset($data['access_token'])) {
            throw new \DomainException('oauth.exchange.failed');
        }

        return (string) $data['access_token'];
    }

    private function redirectUri(): string
    {
        return rtrim($this->oauthRedirectBaseUrl, '/') . '/api/oauth/google/callback';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
