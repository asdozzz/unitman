<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter\GiteaFamilyAdapter;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter\GithubAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GiteaFamilyAdapterTest extends TestCase
{
    private GiteaFamilyAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new GiteaFamilyAdapter(new GithubAdapter());
    }

    #[Test]
    public function podderzhivaet_tolko_gitea_semeytsvo(): void
    {
        self::assertTrue($this->adapter->isSupport(RepoType::GITEA));
        self::assertTrue($this->adapter->isSupport(RepoType::FORGEJO));
        self::assertTrue($this->adapter->isSupport(RepoType::CODEBERG));
        self::assertFalse($this->adapter->isSupport(RepoType::GITHUB));
        self::assertFalse($this->adapter->isSupport(RepoType::GITLAB));
    }

    #[Test]
    public function merged_pull_request_daet_sobitie_sliyaniya(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload([
                'action' => 'closed',
                'pull_request' => [
                    'merged' => true,
                    'merged_at' => '2026-11-20T10:00:00+00:00',
                    'head' => ['ref' => 'feature/123'],
                    'base' => ['ref' => 'main'],
                ],
            ]),
            'main',
        );

        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $dannie->tipSobitiya);
        self::assertSame('feature/123', $dannie->vetka);
        self::assertSame('main', $dannie->celVetka);
    }

    #[Test]
    public function push_obnovlyaet_kod(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload([
                'ref' => 'refs/heads/feature/123',
                'created' => false,
                'deleted' => false,
                'commits' => [
                    ['message' => 'change', 'timestamp' => '2026-11-20T10:00:00+00:00'],
                ],
            ]),
            'main',
        );

        self::assertSame(TipSobitiya::KOD_OBNOVLEN, $dannie->tipSobitiya);
        self::assertSame('feature/123', $dannie->vetka);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function payload(array $data): SobitiePayload
    {
        return new SobitiePayload((string)json_encode($data), $data);
    }
}
