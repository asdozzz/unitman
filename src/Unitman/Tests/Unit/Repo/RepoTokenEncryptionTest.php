<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Repo;

use App\Unitman\Business\Model\Repo;
use App\Unitman\Business\Model\Repo\RepoTokenHint;
use App\Unitman\Infra\Adapter\PersonalTokenOpenSslShifr;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Контракт шифрования токенов репозитория (WS-2).
 *
 * Проверяет round-trip AES-256-GCM с AAD = repoId, провал при чужом AAD,
 * правило tokenHint и отсутствие открытого токена в сообщениях ошибок.
 */
final class RepoTokenEncryptionTest extends TestCase
{
    private const TOKEN = 'TESTTOKENEXAMPLEVALUE';

    private function shifr(): PersonalTokenOpenSslShifr
    {
        // 32 нулевых байта в base64 — синтетический ключ только для теста.
        return new PersonalTokenOpenSslShifr(base64_encode(str_repeat("\0", 32)));
    }

    #[Test]
    public function round_trip_with_aad_repo_id(): void
    {
        $shifr = $this->shifr();
        $repoId = 'repo-1';

        $cipher = $shifr->zashifrovat(self::TOKEN, $repoId);

        self::assertNotSame(self::TOKEN, $cipher);
        self::assertStringNotContainsString(self::TOKEN, $cipher);
        self::assertSame(self::TOKEN, $shifr->rasshifrovat($cipher, $repoId));

        $repo = Repo::addRepo('repo-1', 'GITLAB', 'repo', 'https://gitlab.example.test', $cipher, RepoTokenHint::fromPlainText(self::TOKEN));

        self::assertSame(self::TOKEN, $repo->rasshifrovatToken($shifr));
        self::assertSame('ALUE', $repo->getTokenHint());
        self::assertNotSame(self::TOKEN, $repo->getCredentials()->url);
    }

    #[Test]
    public function wrong_repo_id_aad_fails(): void
    {
        $shifr = $this->shifr();
        $cipher = $shifr->zashifrovat(self::TOKEN, 'repo-1');

        $this->expectException(\DomainException::class);
        $shifr->rasshifrovat($cipher, 'repo-2');
    }

    #[Test]
    public function hint_rule_masks_short_tokens(): void
    {
        self::assertSame('ALUE', RepoTokenHint::fromPlainText(self::TOKEN));
        self::assertSame('5678', RepoTokenHint::fromPlainText('12345678'));
        self::assertSame('', RepoTokenHint::fromPlainText('1234567'));
        self::assertSame('', RepoTokenHint::fromPlainText(''));
        self::assertSame('', RepoTokenHint::fromPlainText(null));
    }

    #[Test]
    public function decryption_error_does_not_leak_token(): void
    {
        $shifr = $this->shifr();

        try {
            $shifr->rasshifrovat('not-a-valid-cipher', 'repo-1');
            self::fail('Ожидалось исключение о повреждённом шифротексте.');
        } catch (\DomainException $e) {
            self::assertStringNotContainsString(self::TOKEN, $e->getMessage());
            self::assertStringNotContainsString('not-a-valid-cipher', $e->getMessage());
        }
    }
}
