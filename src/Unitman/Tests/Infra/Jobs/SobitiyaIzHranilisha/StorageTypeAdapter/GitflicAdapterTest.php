<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\Repo\RepoType;
use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter\GitflicAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GitflicAdapterTest extends TestCase
{
    private GitflicAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new GitflicAdapter();
    }

    #[Test]
    public function podderzhivaet_tolko_gitflic(): void
    {
        self::assertTrue($this->adapter->isSupport(RepoType::GITFLIC));
        self::assertFalse($this->adapter->isSupport(RepoType::GITEA));
    }

    #[Test]
    public function sobitie_merge_daet_sliyanie(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload([
                'event' => 'MERGE',
                'sourceBranch' => 'feature/123',
                'targetBranch' => 'main',
                'timestamp' => '2026-11-20T10:00:00+00:00',
            ]),
            'main',
        );

        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $dannie->tipSobitiya);
        self::assertSame('feature/123', $dannie->vetka);
        self::assertSame('main', $dannie->celVetka);
    }

    #[Test]
    public function sozdanie_vetki_opredelyaetsya_po_eventu(): void
    {
        $payload = $this->payload([
            'event' => 'BRANCH_CREATE',
            'branch' => ['name' => 'refs/heads/feature/123'],
        ]);

        self::assertTrue($this->adapter->esliValidnoeSobitie($payload));

        $dannie = $this->adapter->poluchitDannieSobitiya($payload, 'main');

        self::assertSame(TipSobitiya::VETKA_SOZDANA, $dannie->tipSobitiya);
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
