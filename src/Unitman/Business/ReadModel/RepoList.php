<?php

namespace App\Unitman\Business\ReadModel;

final class RepoList
{
    public function __construct(
        public string $id,
        public string $type,
        public string $name,
        public string $repoUrl,
        public string $token,
        public bool $confirmed
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
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public function changeCredentials(string $url, string $token): void
    {
        $this->repoUrl = $url;
        $this->token = $token;
        $this->confirmed = false;
    }

    public function confirmAccess(): void
    {
        $this->confirmed = true;
    }
}
