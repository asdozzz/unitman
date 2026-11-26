<?php

declare(strict_types=1);

namespace App\Account\Infra\OAuth;

use App\Account\Business\Port\OAuthProvayder;
use App\Account\Business\ReadModel\OAuthUserReadModel;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GithubOAuthProvayder implements OAuthProvayder
{
    private const AUTHORIZATION_URL = 'https://github.com/login/oauth/authorize';
    private const TOKEN_URL = 'https://github.com/login/oauth/access_token';
    private const USER_URL = 'https://api.github.com/user';
    private const EMAILS_URL = 'https://api.github.com/user/emails';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $oauthGithubClientId,
        private readonly string $oauthGithubClientSecret,
        private readonly string $oauthRedirectBaseUrl,
    ) {
    }

    public function imya(): string
    {
        return 'github';
    }

    public function nastroen(): bool
    {
        return trim($this->oauthGithubClientId) !== ''
            && trim($this->oauthGithubClientSecret) !== ''
            && trim($this->oauthRedirectBaseUrl) !== '';
    }

    public function ssylkaAvtorizacii(string $state, string $codeVerifier): string
    {
        $params = [
            'client_id' => $this->oauthGithubClientId,
            'redirect_uri' => $this->redirectUri(),
            'scope' => 'read:user user:email',
            'state' => $state,
            'allow_signup' => 'true',
        ];

        return self::AUTHORIZATION_URL . '?' . http_build_query($params);
    }

    public function poluchitPolzovatelya(string $code, string $codeVerifier): OAuthUserReadModel
    {
        $token = $this->exchangeCode($code);

        $user = $this->httpClient->request('GET', self::USER_URL, [
            'headers' => $this->apiHeaders($token),
        ])->toArray(false);

        $emails = $this->httpClient->request('GET', self::EMAILS_URL, [
            'headers' => $this->apiHeaders($token),
        ])->toArray(false);

        $email = $this->verifiedEmail($emails);

        if ($email === null) {
            throw new \DomainException('oauth.email.not_verified');
        }

        $nickname = $user['name'] ?? $user['login'] ?? null;

        return new OAuthUserReadModel(
            (string) ($user['id'] ?? $email),
            $email,
            $nickname !== null ? (string) $nickname : null,
        );
    }

    private function exchangeCode(string $code): string
    {
        $data = $this->httpClient->request('POST', self::TOKEN_URL, [
            'headers' => ['Accept' => 'application/json'],
            'body' => [
                'client_id' => $this->oauthGithubClientId,
                'client_secret' => $this->oauthGithubClientSecret,
                'code' => $code,
                'redirect_uri' => $this->redirectUri(),
            ],
        ])->toArray(false);

        if (!isset($data['access_token'])) {
            throw new \DomainException('oauth.exchange.failed');
        }

        return (string) $data['access_token'];
    }

    /**
     * @return array<string, string>
     */
    private function apiHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'Unitman',
        ];
    }

    /**
     * @param array<int, mixed> $emails
     */
    private function verifiedEmail(array $emails): ?string
    {
        $verified = null;

        foreach ($emails as $email) {
            if (!is_array($email)) {
                continue;
            }

            if (($email['verified'] ?? false) !== true) {
                continue;
            }

            if (($email['primary'] ?? false) === true) {
                return (string) $email['email'];
            }

            $verified ??= (string) $email['email'];
        }

        return $verified;
    }

    private function redirectUri(): string
    {
        return rtrim($this->oauthRedirectBaseUrl, '/') . '/api/oauth/github/callback';
    }
}
