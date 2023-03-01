<?php

namespace App\Unitman\Business\ReadModel;

final class RepoList
{
    public function __construct(
        private string $id,
        private string $type,
        private string $name,
        private string $repoUrl,
        private string $repoLogin,
        private string $repoPassword,
        private bool $confirmed
    )
    {
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getRepoUrl(): string
    {
        return $this->repoUrl;
    }

    /**
     * @return string
     */
    public function getRepoLogin(): string
    {
        return $this->repoLogin;
    }

    /**
     * @return string
     */
    public function getRepoPassword(): string
    {
        return $this->repoPassword;
    }

    /**
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function changeCredentials(string $url, string $login, string $password): void
    {
        $this->repoUrl = $url;
        $this->repoLogin = $login;
        $this->repoPassword = $password;
        $this->confirmed = false;
    }

    public function confirmAccess(): void
    {
        $this->confirmed = true;
    }
}
