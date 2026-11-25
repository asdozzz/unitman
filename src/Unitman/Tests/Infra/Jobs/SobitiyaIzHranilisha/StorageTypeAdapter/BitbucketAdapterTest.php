<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter\BitbucketAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BitbucketAdapterTest extends TestCase
{
    private BitbucketAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new BitbucketAdapter();
    }

    #[Test]
    public function podderzhivaet_tolko_bitbucket(): void
    {
        self::assertTrue($this->adapter->isSupport(RepoType::BITBUCKET));
        self::assertFalse($this->adapter->isSupport(RepoType::GITHUB));
    }

    #[Test]
    public function merged_pull_request_daet_sliyanie(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload([
                'pullrequest' => [
                    'state' => 'MERGED',
                    'merged_on' => '2026-11-20T10:00:00+00:00',
                    'source' => ['branch' => ['name' => 'feature/123']],
                    'destination' => ['branch' => ['name' => 'main']],
                ],
            ]),
            'main',
        );

        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $dannie->tipSobitiya);
        self::assertSame('feature/123', $dannie->vetka);
        self::assertSame('main', $dannie->celVetka);
    }

    #[Test]
    public function push_v_main_s_merge_kommitom_daet_sliyanie(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload([
                'push' => [
                    'changes' => [[
                        'new' => ['name' => 'main', 'type' => 'branch'],
                        'created' => false,
                        'closed' => false,
                        'commits' => [[
                            'message' => 'Merged in feature/123 (pull request #12)',
                            'date' => '2026-11-20T10:00:00+00:00',
                        ]],
                    ]],
                ],
            ]),
            'main',
        );

        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $dannie->tipSobitiya);
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
