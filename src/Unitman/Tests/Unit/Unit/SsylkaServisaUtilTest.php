<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Utils\SsylkaServisaUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Единое правило построения ссылки на сервис юнита:
 * `http`, `ws`, `wss` идут через proxy host (для ws/wss схема выбирается по
 * схеме proxy: `https` → `wss`, иначе `ws`), любой другой протокол — напрямую
 * на юнит.
 */
final class SsylkaServisaUtilTest extends TestCase
{
    public static function pryamieTipiProvider(): array
    {
        return [
            'tcp' => ['tcp', 'tcp://myunit.myproject:8000'],
            'другой протокол' => ['udp', 'udp://myunit.myproject:8000'],
            'uppercase tcp' => ['TCP', 'tcp://myunit.myproject:8000'],
            'с пробелами' => ['  Tcp  ', 'tcp://myunit.myproject:8000'],
        ];
    }

    #[Test]
    #[DataProvider('pryamieTipiProvider')]
    public function pryamie_protokoli_stroyatsya_na_yunit(string $type, string $expected): void
    {
        $this->assertSame(
            $expected,
            SsylkaServisaUtil::sformirovat($type, 8000, 'myunit', 'myproject', 'https://unitman.example.ru')
        );
    }

    #[Test]
    public function pryamie_protokoli_ne_zavisyat_ot_proxy_hosta(): void
    {
        $this->assertSame(
            'tcp://myunit.myproject:8000',
            SsylkaServisaUtil::sformirovat('tcp', 8000, 'myunit', 'myproject', null)
        );
    }

    #[Test]
    public function ws_stroitsya_cherez_https_proxy_kak_wss(): void
    {
        $this->assertSame(
            'wss://8080.myunit.myproject.unitman.example.ru',
            SsylkaServisaUtil::sformirovat('ws', 8080, 'myunit', 'myproject', 'https://unitman.example.ru')
        );
    }

    #[Test]
    public function ws_stroitsya_cherez_http_proxy_kak_ws(): void
    {
        $this->assertSame(
            'ws://8080.myunit.myproject.unitman.example.ru',
            SsylkaServisaUtil::sformirovat('ws', 8080, 'myunit', 'myproject', 'http://unitman.example.ru')
        );
    }

    #[Test]
    public function wss_normaliziruetsya_i_stroitsya_cherez_proxy(): void
    {
        $this->assertSame(
            'wss://8080.myunit.myproject.unitman.example.ru',
            SsylkaServisaUtil::sformirovat('wss', 8080, 'myunit', 'myproject', 'https://unitman.example.ru')
        );
    }

    #[Test]
    public function ws_s_uppercase_i_probelami_normaliziruetsya(): void
    {
        $this->assertSame(
            'wss://8080.myunit.myproject.unitman.example.ru',
            SsylkaServisaUtil::sformirovat('  Ws  ', 8080, 'myunit', 'myproject', 'https://unitman.example.ru')
        );
    }

    #[Test]
    public function ws_uchitivaet_port_proxy_hosta(): void
    {
        $this->assertSame(
            'wss://8080.myunit.myproject.unitman.example.ru:8443',
            SsylkaServisaUtil::sformirovat('ws', 8080, 'myunit', 'myproject', 'https://unitman.example.ru:8443')
        );
    }

    #[Test]
    public function http_stroitsya_cherez_proxy_host(): void
    {
        $this->assertSame(
            'https://8080.myunit.myproject.unitman.example.ru',
            SsylkaServisaUtil::sformirovat('http', 8080, 'myunit', 'myproject', 'https://unitman.example.ru')
        );
    }

    #[Test]
    public function http_uchitivaet_port_proxy_hosta(): void
    {
        $this->assertSame(
            'https://8080.myunit.myproject.unitman.example.ru:8443',
            SsylkaServisaUtil::sformirovat('http', 8080, 'myunit', 'myproject', 'https://unitman.example.ru:8443')
        );
    }

    #[Test]
    public function pustoi_tip_traktuetsya_kak_http(): void
    {
        $this->assertSame(
            'https://8080.myunit.myproject.unitman.example.ru',
            SsylkaServisaUtil::sformirovat(null, 8080, 'myunit', 'myproject', 'https://unitman.example.ru')
        );
    }

    #[Test]
    public function port_strokoi_privoditsya_k_celomu_dlya_ws(): void
    {
        $this->assertSame(
            'wss://8080.myunit.myproject.unitman.example.ru',
            SsylkaServisaUtil::sformirovat('ws', '8080', 'myunit', 'myproject', 'https://unitman.example.ru')
        );
    }

    #[Test]
    public function http_bez_validnogo_proxy_hosta_daet_oshibku(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.invalid_proxy_host');

        SsylkaServisaUtil::sformirovat('http', 8080, 'myunit', 'myproject', 'not-a-url');
    }

    #[Test]
    public function ws_bez_validnogo_proxy_hosta_daet_oshibku(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.invalid_proxy_host');

        SsylkaServisaUtil::sformirovat('ws', 8080, 'myunit', 'myproject', null);
    }
}
