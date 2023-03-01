<?php

namespace App\Unitman\Infra\Adapter\StorageApiAdapter\GithubAdapter;

use App\Unitman\Business\Model\Repo;
use Github\AuthMethod;
use Github\Client;
use Symfony\Component\HttpClient\HttplugClient;

final class GithubClientFactory
{
    public function makeClient(Repo $repo): Client
    {
        $client = Client::createWithHttpClient(new HttplugClient());
        $client->authenticate($repo->getCredentials()->login, $repo->getCredentials()->password, AuthMethod::ACCESS_TOKEN);
        return $client;
    }
}
