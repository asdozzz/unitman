<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter;

use App\Unitman\Business\Model\SobitieIzHranilisha\SobitiePayload;
use App\Unitman\Business\Model\SobitieIzHranilisha\TipSobitiya;
use App\Unitman\Infra\Jobs\SobitiyaIzHranilisha\StorageTypeAdapter\GithubAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GithubAdapterTest extends TestCase
{
    private const MAIN_BRANCH = 'main';
    private const MERGED_AT = '2026-11-20T10:00:00+00:00';

    private GithubAdapter $adapter;

    protected function setUp(): void
    {
        $this->adapter = new GithubAdapter();
    }

    #[Test]
    public function pull_request_v_main_daet_sobitie_sliyaniya(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload($this->pullRequest('feature/123', self::MAIN_BRANCH, true)),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $dannie->tipSobitiya);
        self::assertSame('feature/123', $dannie->vetka);
        self::assertSame(self::MAIN_BRANCH, $dannie->celVetka);
        self::assertSame((new \DateTimeImmutable(self::MERGED_AT))->getTimestamp(), $dannie->unixtime);
    }

    #[Test]
    public function pull_request_v_druguyu_vetku_ostavlyaet_cel_dlya_proverki_activity(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload($this->pullRequest('feature/123', 'develop', true)),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $dannie->tipSobitiya);
        self::assertSame('develop', $dannie->celVetka);
    }

    #[Test]
    public function zakritii_bez_merdzha_pull_request_ne_validen(): void
    {
        $payload = $this->payload($this->pullRequest('feature/123', self::MAIN_BRANCH, false));

        self::assertFalse($this->adapter->esliValidnoeSobitie($payload));
    }

    #[Test]
    public function push_v_main_s_merge_pull_request_daet_sobitie_sliyaniya(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload($this->push(self::MAIN_BRANCH, [
                'Merge pull request #12 from acme/feature/123',
            ])),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $dannie->tipSobitiya);
        self::assertSame('feature/123', $dannie->vetka);
        self::assertSame(self::MAIN_BRANCH, $dannie->celVetka);
    }

    #[Test]
    public function push_v_main_s_lokalnim_merge_kommitom_daet_sobitie_sliyaniya(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload($this->push(self::MAIN_BRANCH, [
                "Merge branch 'feature/123' into main",
            ])),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::VETKA_SMERZHENA, $dannie->tipSobitiya);
        self::assertSame('feature/123', $dannie->vetka);
    }

    #[Test]
    public function merzh_main_v_feature_ne_schitaetsya_sliyaniem_v_main(): void
    {
        $dannie = $this->adapter->poluchitDannieSobitiya(
            $this->payload($this->push(self::MAIN_BRANCH, [
                "Merge branch 'main' into feature/123",
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
                'Merge pull request #12 from acme/feature/inner',
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
                'ref' => 'refs/heads/feature/123',
                'created' => true,
                'deleted' => false,
                'commits' => [],
            ]),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::VETKA_SOZDANA, $sozdanie->tipSobitiya);
        self::assertSame('feature/123', $sozdanie->vetka);

        $udalenie = $this->adapter->poluchitDannieSobitiya(
            $this->payload([
                'ref' => 'refs/heads/feature/123',
                'created' => false,
                'deleted' => true,
                'commits' => [],
            ]),
            self::MAIN_BRANCH,
        );

        self::assertSame(TipSobitiya::VETKA_UDALENA, $udalenie->tipSobitiya);
        self::assertSame('feature/123', $udalenie->vetka);
    }

    /**
     * @return array<string, mixed>
     */
    private function pullRequest(string $head, string $base, bool $merged): array
    {
        return [
            'action' => 'closed',
            'pull_request' => [
                'merged' => $merged,
                'merged_at' => self::MERGED_AT,
                'head' => ['ref' => $head],
                'base' => ['ref' => $base],
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
            'ref' => 'refs/heads/' . $vetka,
            'created' => false,
            'deleted' => false,
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
