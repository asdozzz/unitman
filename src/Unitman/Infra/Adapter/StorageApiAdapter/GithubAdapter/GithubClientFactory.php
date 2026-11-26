<?php

namespace App\Unitman\Infra\Adapter\StorageApiAdapter\GithubAdapter;

use App\Unitman\Business\Model\Repo;
use Github\AuthMethod;
use Github\Client;
use Symfony\Component\HttpClient\HttplugClient;

final class GithubClientFactory
{
    public function __construct(
        private readonly \App\Unitman\Business\Port\UmeetShifrovatSekrety $shifr,
    ) {
    }

    public function makeClient(Repo $repo): Client
    {
        return $this->makeClientWithToken($repo, $repo->rasshifrovatToken($this->shifr));
    }

    public function makeClientWithToken(Repo $repo, string $token): Client
    {
        $client = Client::createWithHttpClient(new HttplugClient());

        $client->authenticate($token, null, AuthMethod::ACCESS_TOKEN);
        return $client;
    }
}
