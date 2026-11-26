<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Infra\Adapter\StorageApiAdapter;

use App\Unitman\Business\Command\Repo\AddRepo;
use App\Unitman\Business\Model\Project\StatusVetki;
use App\Unitman\Business\Model\Repo;
use App\Unitman\Infra\Adapter\StorageApiAdapter\CodebergAdapter;
use App\Unitman\Infra\Adapter\StorageApiAdapter\ForgejoAdapter;
use App\Unitman\Infra\Adapter\StorageApiAdapter\GiteaAdapter;
use App\Unitman\Tests\Support\PlainTextShifr;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GiteaFamilyAdapterTest extends TestCase
{
    #[Test]
    public function kazhdiy_adapter_podderzhivaet_svoy_tip(): void
    {
        $client = new MockHttpClient();

        self::assertTrue((new GiteaAdapter($client, $this->shifr()))->isSupport(Repo\RepoType::GITEA));
        self::assertFalse((new GiteaAdapter($client, $this->shifr()))->isSupport(Repo\RepoType::FORGEJO));

        self::assertTrue((new ForgejoAdapter($client, $this->shifr()))->isSupport(Repo\RepoType::FORGEJO));
        self::assertFalse((new ForgejoAdapter($client, $this->shifr()))->isSupport(Repo\RepoType::CODEBERG));

        self::assertTrue((new CodebergAdapter($client, $this->shifr()))->isSupport(Repo\RepoType::CODEBERG));
        self::assertFalse((new CodebergAdapter($client, $this->shifr()))->isSupport(Repo\RepoType::GITEA));
    }

    #[Test]
    public function url_hranilisha_normalizuetsya_i_codeberg_fiksirovan(): void
    {
        $client = new MockHttpClient();

        self::assertSame(
            'https://gitea.example',
            (new GiteaAdapter($client, $this->shifr()))->poluchitUrlHranilisha('https://gitea.example/api/v1/'),
        );
        self::assertSame(
            'https://codeberg.org',
            (new CodebergAdapter($client, $this->shifr()))->poluchitUrlHranilisha(null),
        );
    }

    #[Test]
    public function proverka_tokena_ispolzuet_endpoint_user(): void
    {
        $ok = new GiteaAdapter(new MockHttpClient(
            static fn(string $method, string $url): MockResponse => new MockResponse(
                str_ends_with($url, '/user') ? '{"login":"tester"}' : '[]',
                ['http_code' => 200],
            ),
        ), $this->shifr());
        self::assertTrue($ok->proveritToken('token', $this->repo('GITEA')));

        $fail = new GiteaAdapter(new MockHttpClient(
            static fn(): MockResponse => new MockResponse('{"message":"unauthorized"}', ['http_code' => 401]),
        ), $this->shifr());
        self::assertFalse($fail->proveritToken('token', $this->repo('GITEA')));
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
                    '[{"merged_at":"2026-11-20T11:00:00Z","head":{"ref":"feature/123"},"base":{"ref":"main"}}]',
                    ['http_code' => 200],
                );
            }

            return new MockResponse('[]', ['http_code' => 200]);
        });

        $sostoyanie = (new GiteaAdapter($client, $this->shifr()))->poluchitSostoyanieVetki(
            $this->repo('GITEA'),
            'acme/api',
            'feature/123',
            'main',
        );

        self::assertSame(StatusVetki::MERGED, $sostoyanie->status);
        self::assertSame((new \DateTimeImmutable('2026-11-20T10:00:00Z'))->getTimestamp(), $sostoyanie->posledniyKommitUnixtime);
    }

    #[Test]
    public function konfig_chitaetsya_iz_raw_endpointa(): void
    {
        $client = new MockHttpClient(
            static fn(): MockResponse => new MockResponse('units: []', ['http_code' => 200]),
        );

        self::assertSame(
            'units: []',
            (new GiteaAdapter($client, $this->shifr()))->poluchitKonfigIzHranilisha($this->repo('GITEA'), 'acme/api', 'main'),
        );
    }

    private function shifr(): PlainTextShifr
    {
        return new PlainTextShifr();
    }

    private function repo(string $type): Repo
    {
        return Repo::addRepo('repo-1', $type, 'repo', 'https://gitea.example', 'token', '');
    }
}
