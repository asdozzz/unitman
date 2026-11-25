<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Infra\Adapter\StorageApiAdapter;

use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Model\Project\StatusVetki;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Infra\Adapter\StorageApiAdapter\GitverseAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GitverseAdapterTest extends TestCase
{
    #[Test]
    public function saas_url_po_umolchaniyu(): void
    {
        $adapter = new GitverseAdapter(new MockHttpClient());

        self::assertSame('https://gitverse.ru', $adapter->poluchitUrlHranilisha(null));
        self::assertTrue($adapter->isSupport(Repo\RepoType::GITVERSE));
        self::assertFalse($adapter->isSupport(Repo\RepoType::GITFLIC));
    }

    #[Test]
    public function sostoyanie_vetki_opredelyaet_merge(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url): MockResponse {
            if (str_contains($url, '/branches/')) {
                return new MockResponse(
                    '{"name":"feature/123","commit":{"timestamp":"2026-11-20T10:00:00Z"}}',
                    ['http_code' => 200],
                );
            }

            if (str_contains($url, '/pulls')) {
                return new MockResponse(
                    '[{"state":"MERGED","merged_at":"2026-11-20T11:00:00Z","head":{"ref":"feature/123"},"base":{"ref":"main"}}]',
                    ['http_code' => 200],
                );
            }

            return new MockResponse('[]', ['http_code' => 200]);
        });

        $sostoyanie = (new GitverseAdapter($client))->poluchitSostoyanieVetki(
            $this->repo(),
            'acme/api',
            'feature/123',
            'main',
        );

        self::assertSame(StatusVetki::MERGED, $sostoyanie->status);
    }

    #[Test]
    public function konfig_dekodiruetsya_iz_base64(): void
    {
        $client = new MockHttpClient(
            static fn(): MockResponse => new MockResponse(
                (string)json_encode([
                    'encoding' => 'base64',
                    'content' => base64_encode('units: []'),
                ]),
                ['http_code' => 200],
            ),
        );

        self::assertSame(
            'units: []',
            (new GitverseAdapter($client))->poluchitKonfigIzHranilisha($this->repo(), 'acme/api', 'main'),
        );
    }

    private function repo(): Repo
    {
        return Repo::addRepo(
            'repo-1',
            new AddRepo('GITVERSE', 'repo', 'token', 'https://gitverse.ru'),
            'https://gitverse.ru',
        );
    }
}
