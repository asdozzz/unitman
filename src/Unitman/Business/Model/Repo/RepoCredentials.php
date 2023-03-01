<?php

namespace App\Unitman\Business\Model\Repo;

final class RepoCredentials
{
    public readonly string $url;
    public readonly string $login;
    public readonly string $password;

    public function __construct(
        string $url,
        string $login,
        string $password
    )
    {
        if (empty($url)) {
            throw new \DomainException('repo.credentials.url_is_empty');
        }

        if (empty($login)) {
            throw new \DomainException('repo.credentials.login_is_empty');
        }

        if (empty($password)) {
            throw new \DomainException('repo.credentials.password_is_empty');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
            throw new \DomainException('repo.credentials.url_invalid');
        }

        $this->url = $url;
        $this->login = $login;
        $this->password = $password;
    }

    function getHash(): string
    {
        return md5($this->url.$this->login.$this->password);
    }
}
