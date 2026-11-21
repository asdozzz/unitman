<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\SobitieIzHranilisha\DannieSobitiya;
use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter\GitlabAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GitlabAdapterTest extends TestCase
{
    private const MAIN_BRANCH = 'main';
    private const MERGED_AT = '2026-11-20T10:00:00+00:00';

    private GitlabAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new GitlabAdapter();
    }

    #[Test]
    public function merge_request_v_main_daet_sobitie_sliyaniya(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload($this->mergeRequest('feature/123', self::MAIN_BRANCH)),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $dannie->tipSobitiya);
        self::assertSame('feature/123', $dannie->vetka);
        self::assertSame(self::MAIN_BRANCH, $dannie->celVetka);
        self::assertSame((new \DateTimeImmutable(self::MERGED_AT))->getTimestamp(), $dannie->unixtime);
    }

    #[Test]
    public function merge_request_v_druguyu_vetku_ostavlyaet_cel_dlya_proverki_activity(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload($this->mergeRequest('feature/123', 'develop')),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $dannie->tipSobitiya);
        self::assertSame('feature/123', $dannie->vetka);
        self::assertSame('develop', $dannie->celVetka);
    }

    #[Test]
    public function otkritii_merge_request_ne_validen(): void
    {
        $payload = $this->payload($this->mergeRequest('feature/123', self::MAIN_BRANCH, 'open', 'opened'));

        self::assertFalse($this->adapter->esliValidnoeSobitie($payload));
    }

    #[Test]
    public function push_v_main_s_merge_kommitom_daet_sobitie_sliyaniya(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload($this->push(self::MAIN_BRANCH, [
                "Merge branch 'feature/123' into 'main'",
            ])),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $dannie->tipSobitiya);
        self::assertSame('feature/123', $dannie->vetka);
        self::assertSame(self::MAIN_BRANCH, $dannie->celVetka);
    }

    #[Test]
    public function push_v_main_s_neskolkimi_vetkami_beret_pervuyu_iz_svezhih(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload($this->push(self::MAIN_BRANCH, [
                "Merge branches 'feature/a' and 'feature/b' into 'main'",
            ])),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $dannie->tipSobitiya);
        self::assertSame('feature/a', $dannie->vetka);
    }

    #[Test]
    public function vlozhennii_merzh_v_feature_ne_schitaetsya_sliyaniem_v_main(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload($this->push(self::MAIN_BRANCH, [
                "Merge branch 'feature/inner' into 'feature/123'",
            ])),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::KOD_OBNOVLEN, $dannie->tipSobitiya);
        self::assertSame(self::MAIN_BRANCH, $dannie->vetka);
    }

    #[Test]
    public function push_v_feature_s_merge_kommitom_ostaetsya_obnovleniem_koda(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload($this->push('feature/123', [
                "Merge branch 'feature/inner' into 'feature/123'",
            ])),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::KOD_OBNOVLEN, $dannie->tipSobitiya);
        self::assertSame('feature/123', $dannie->vetka);
    }

    #[Test]
    public function push_bez_merge_kommita_ostaetsya_obnovleniem_koda(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload($this->push(self::MAIN_BRANCH, ['obychnyi commit'])),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::KOD_OBNOVLEN, $dannie->tipSobitiya);
        self::assertSame(self::MAIN_BRANCH, $dannie->vetka);
    }

    #[Test]
    public function sozdanie_i_udalenie_vetki_ne_regressiruyut(): void
    {
        $sozdanie = $this->adapter->poluchitDannieSobitiya(
            $this->payload([
                'event_name' => 'push',
                'ref' => 'refs/heads/feature/123',
                'before' => '0000000000000000000000000000000000000000',
                'after' => 'bf2c3d4e',
            ]),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::VETKA_SOZDANA, $sozdanie->tipSobitiya);
        self::assertSame('feature/123', $sozdanie->vetka);

        $udalenie = $this->adapter->poluchitDannieSobitiya(
            $this->payload([
                'event_name' => 'push',
                'ref' => 'refs/heads/feature/123',
                'before' => 'ae1b2c3d',
                'after' => '0000000000000000000000000000000000000000',
            ]),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::VETKA_UDALENA, $udalenie->tipSobitiya);
        self::assertSame('feature/123', $udalenie->vetka);
    }

    #[Test]
    public function push_v_main_validen_a_teg_net(): void
    {
        self::assertTrue($this->adapter->esliValidnoeSobitie(
            $this->payload($this->push(self::MAIN_BRANCH, ['commit']))
        ));

        self::assertFalse($this->adapter->esliValidnoeSobitie(
            $this->payload([
                'event_name' => 'push',
                'ref' => 'refs/tags/v1.0.0',
                'commits' => [['timestamp' => '2026-11-20T10:00:00+00:00']],
            ])
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeRequest(
        string $source,
        string $target,
        string $state = 'merged',
        string $action = 'merge',
    ): array {
        return [
            'object_kind' => 'merge_request',
            'object_attributes' => [
                'action' => $action,
                'state' => $state,
                'source_branch' => $source,
                'target_branch' => $target,
                'merged_at' => self::MERGED_AT,
            ],
        ];
    }

    /**
     * @param string[] $messages
     * @return array<string, mixed>
     */
    private function push(string $vetka, array $messages): array
    {
        return [
            'event_name' => 'push',
            'ref' => 'refs/heads/' . $vetka,
            'before' => 'ae1b2c3d',
            'after' => 'bf2c3d4e',
            'commits' => array_map(
                static fn(string $message): array => [
                    'message' => $message,
                    'timestamp' => '2026-11-20T10:00:00+00:00',
                ],
                $messages,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function payload(array $data): SobitiePayload
    {
        return new SobitiePayload((string)json_encode($data), $data);
    }
}
