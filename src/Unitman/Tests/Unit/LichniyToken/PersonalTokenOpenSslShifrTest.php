<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\LichniyToken;

use App\Unitman\Infra\Adapter\PersonalTokenOpenSslShifr;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PersonalTokenOpenSslShifrTest extends TestCase
{
    private const KEY_PLAIN = 'test-only-personal-token-key-32b';

    private const AAD = 'account-1:token-1';

    #[Test]
    public function shifrovanie_i_rasshifrovanie_sovpadayut(): void
    {
        $shifr = new PersonalTokenOpenSslShifr(base64_encode(self::KEY_PLAIN));
        $secret = 'glpat-abcdef123456';

        $cipher = $shifr->zashifrovat($secret, self::AAD);

        self::assertStringNotContainsString($secret, $cipher);
        self::assertSame($secret, $shifr->rasshifrovat($cipher, self::AAD));
    }

    #[Test]
    public function chuzhoy_aad_ne_rasshifrovyvaetsya(): void
    {
        $shifr = new PersonalTokenOpenSslShifr(base64_encode(self::KEY_PLAIN));
        $cipher = $shifr->zashifrovat('secret', self::AAD);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('lichniy_token.decrypt_failed');

        $shifr->rasshifrovat($cipher, 'account-2:token-1');
    }

    #[Test]
    public function pustoy_klyuch_ne_lomaet_konstruktor(): void
    {
        // Отсутствие ключа не должно мешать сборке контейнера и не связанным
        // с фичей командам — ошибка возникает только при использовании.
        $shifr = new PersonalTokenOpenSslShifr('');

        self::assertInstanceOf(PersonalTokenOpenSslShifr::class, $shifr);
    }

    #[Test]
    public function pustoy_klyuch_padaet_pri_shifrovanii(): void
    {
        $shifr = new PersonalTokenOpenSslShifr('');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('lichniy_token.key_not_configured');

        $shifr->zashifrovat('secret', self::AAD);
    }

    #[Test]
    public function pustoy_klyuch_padaet_pri_rasshifrovanii(): void
    {
        $shifr = new PersonalTokenOpenSslShifr('');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('lichniy_token.key_not_configured');

        $shifr->rasshifrovat('anything', self::AAD);
    }

    #[Test]
    public function klyuch_nevernoy_dliny_zapreshchen_pri_ispolzovanii(): void
    {
        $shifr = new PersonalTokenOpenSslShifr(base64_encode(str_repeat('k', 16)));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('lichniy_token.invalid_key');

        $shifr->zashifrovat('secret', self::AAD);
    }
}
