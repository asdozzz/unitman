<?php

namespace App\Unitman\Business\Model\Repo;

final class RepoCredentials
{
    public readonly string $url;
    public readonly string $token;

    public function __construct(
        string $url,
        string $token,
    )
    {
        if (empty($url)) {
            throw new \DomainException('repo.credentials.url_is_empty');
        }

        if (empty($token)) {
            throw new \DomainException('repo.credentials.token_is_empty');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
            throw new \DomainException('repo.credentials.url_invalid');
        }

        $this->url = $url;
        $this->token = $token;
    }

    function getHash(): string
    {
        return md5($this->url.$this->token);
    }
}
