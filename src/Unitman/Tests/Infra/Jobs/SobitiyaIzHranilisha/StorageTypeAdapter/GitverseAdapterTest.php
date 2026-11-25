<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter\GitverseAdapter;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter\GithubAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GitverseAdapterTest extends TestCase
{
    private GitverseAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new GitverseAdapter(new GithubAdapter());
    }

    #[Test]
    public function podderzhivaet_tolko_gitverse(): void
    {
        self::assertTrue($this->adapter->isSupport(RepoType::GITVERSE));
        self::assertFalse($this->adapter->isSupport(RepoType::GITFLIC));
    }

    #[Test]
    public function merged_pull_request_daet_sliyanie(): void
    {
        $data = [
            'action' => 'closed',
            'pull_request' => [
                'merged' => true,
                'merged_at' => '2026-11-20T10:00:00+00:00',
                'head' => ['ref' => 'feature/123'],
                'base' => ['ref' => 'main'],
            ],
        ];

        $dannie = $this->adapter->poluchitDannieSobitiya(
            new SobitiePayload((string)json_encode($data), $data),
            'main',
        );

        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $dannie->tipSobitiya);
        self::assertSame('feature/123', $dannie->vetka);
        self::assertSame('main', $dannie->celVetka);
    }
}
