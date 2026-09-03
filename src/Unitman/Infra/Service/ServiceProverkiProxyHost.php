<?php

namespace App\Unitman\Infra\Service;

use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ServiceProverkiProxyHost
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    function proverit(?string $proxyHost): bool
    {
        if (empty($proxyHost)) {
            throw new \DomainException('Proxy host is empty');
        }

        $response = $this->httpClient->request('GET', $proxyHost);

        if ($response->getStatusCode() !== 200) {
            throw new \DomainException('Proxy host error: ' . $response->getStatusCode());
        }

        $content = $response->getContent();

        if (strpos($content, 'Unitman Proxy Host') === false) {
            throw new \DomainException('Host is not unitman proxy host');
        }

        return true;
    }
}
