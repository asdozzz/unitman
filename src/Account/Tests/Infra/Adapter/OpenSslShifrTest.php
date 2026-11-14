<?php

declare(strict_types=1);

namespace App\Account\Tests\Infra\Adapter;

use App\Account\Infra\Adapter\OpenSslShifr;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Шифрование реквизитов OpenCode.
 *
 * Проверяет контракт `UmeetShifrovatSekrety`: ключ не лежит в БД открытым
 * текстом, шифротекст привязан к записи (AAD), а испорченные или чужие
 * данные не расшифровываются тихо.
 */
final class OpenSslShifrTest extends TestCase
{
    /** base64 от 32 байт ("test-only-opencode-key-32bytes!!"). */
    private const VALID_KEY = 'dGVzdC1vbmx5LW9wZW5jb2RlLWtleS0zMmJ5dGVzISE=';

    private const AAD = 'account-1:credential-1';

    #[Test]
    public function zashifrovannyi_tekst_rasshifrovyvaetsya_obratno(): void
    {
        $shifr = new OpenSslShifr(self::VALID_KEY);
        $secret = 'sk-live-abcdef123456';

        $cipher = $shifr->zashifrovat($secret, self::AAD);

        // Плейнтекста в шифротексте быть не должно.
        self::assertStringNotContainsString($secret, $cipher);
        self::assertNotSame($secret, $cipher);
        self::assertSame($secret, $shifr->rasshifrovat($cipher, self::AAD));
    }

    #[Test]
    public function odin_i_tot_zhe_tekst_shifruetsya_po_raznomu(): void
    {
        $shifr = new OpenSslShifr(self::VALID_KEY);

        $first = $shifr->zashifrovat('same-secret', self::AAD);
        $second = $shifr->zashifrovat('same-secret', self::AAD);

        // Случайный nonce: одинаковые плейнтексты дают разные шифротексты,
        // иначе по БД можно было бы сравнить секреты между собой.
        self::assertNotSame($first, $second);
        self::assertSame('same-secret', $shifr->rasshifrovat($second, self::AAD));
    }

    #[Test]
    public function pustoi_tekst_shifruetsya_i_rasshifrovyvaetsya(): void
    {
        $shifr = new OpenSslShifr(self::VALID_KEY);

        $cipher = $shifr->zashifrovat('', self::AAD);

        // Пустой плейнтекст даёт ровно nonce + tag: граница проверки
        // длины не должна отбрасывать структурно корректный шифротекст.
        self::assertSame('', $shifr->rasshifrovat($cipher, self::AAD));
    }

    #[Test]
    public function chuzhoi_aad_ne_rasshifrovyvaetsya(): void
    {
        $shifr = new OpenSslShifr(self::VALID_KEY);
        $cipher = $shifr->zashifrovat('secret', self::AAD);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.decrypt_failed');

        // Подмена записи в БД не должна давать чужой ключ.
        $shifr->rasshifrovat($cipher, 'account-2:credential-1');
    }

    #[Test]
    public function isporchennyi_shifrotekst_ne_rasshifrovyvaetsya(): void
    {
        $shifr = new OpenSslShifr(self::VALID_KEY);
        $cipher = $shifr->zashifrovat('secret', self::AAD);

        $raw = base64_decode($cipher, true);
        self::assertIsString($raw);

        // Портим последний байт шифротекста: тег GCM не сойдётся.
        $raw[\strlen($raw) - 1] = \chr(\ord($raw[\strlen($raw) - 1]) ^ 0xFF);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.decrypt_failed');

        $shifr->rasshifrovat(base64_encode($raw), self::AAD);
    }

    #[Test]
    public function slishkom_korotkii_shifrotekst_ne_rasshifrovyvaetsya(): void
    {
        $shifr = new OpenSslShifr(self::VALID_KEY);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.decrypt_failed');

        // nonce(12) + tag(16) без самого шифротекста.
        $shifr->rasshifrovat(base64_encode(str_repeat('a', 28)), self::AAD);
    }

    #[Test]
    public function ne_validnyi_base64_ne_rasshifrovyvaetsya(): void
    {
        $shifr = new OpenSslShifr(self::VALID_KEY);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.decrypt_failed');

        $shifr->rasshifrovat('!!!not-base64!!!', self::AAD);
    }

    #[Test]
    public function pustoi_klyuch_zapreshchaet_rabotu(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.key_not_configured');

        // Пустой ключ = фича выключена. Падать нужно на конструкторе,
        // иначе секрет мог бы уехать в БД незашифрованным.
        new OpenSslShifr('');
    }

    #[Test]
    public function klyuch_ne_v_base64_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.invalid_key');

        new OpenSslShifr('not-a-base64-key');
    }

    #[Test]
    public function klyuch_nevernoi_dliny_zapreshchen(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('account.opencode_credential.invalid_key');

        // 16 байт вместо 32.
        new OpenSslShifr(base64_encode(str_repeat('k', 16)));
    }
}
