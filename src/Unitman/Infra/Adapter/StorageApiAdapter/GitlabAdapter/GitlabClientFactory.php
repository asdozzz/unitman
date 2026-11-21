<?php

namespace App\Unitman\Infra\Adapter\StorageApiAdapter\GitlabAdapter;

use App\Unitman\Business\Model\Repo;
use Gitlab\Client;

final class GitlabClientFactory
{
    public function makeClient(Repo $repo): Client
    {
        return $this->makeClientWithToken($repo, $repo->getCredentials()->token);
    }

    public function makeClientWithToken(Repo $repo, string $token): Client
    {
        $client = new Client();
        $client->setUrl($repo->getCredentials()->url);
        $client->authenticate($token, Client::AUTH_HTTP_TOKEN);

        return $client;
    }
}
