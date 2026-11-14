<?php

declare(strict_types=1);

namespace App\Unitman\Tests\Unit\Unit;

use App\Unitman\Business\Utils\SsylkaServisaUtil;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Единое правило построения ссылки на сервис юнита:
 * `http` идёт через proxy host, любой другой протокол — напрямую на юнит.
 */
final class SsylkaServisaUtilTest extends TestCase
{
    public static function neHttpTipiProvider(): array
    {
        return [
            'ws' => ['ws', 'ws://myunit.myproject:8000'],
            'tcp' => ['tcp', 'tcp://myunit.myproject:8000'],
            'uppercase ws' => ['WS', 'ws://myunit.myproject:8000'],
            'с пробелами' => ['  Ws  ', 'ws://myunit.myproject:8000'],
        ];
    }

    #[Test]
    #[DataProvider('neHttpTipiProvider')]
    public function ne_http_stroitsya_po_shablonu(string $type, string $expected): void
    {
        $this->assertSame(
            $expected,
            SsylkaServisaUtil::sformirovat($type, 8000, 'myunit', 'myproject', 'https://unitman.example.ru')
        );
    }

    #[Test]
    public function ne_http_ne_zavisit_ot_proxy_hosta(): void
    {
        $this->assertSame(
            'ws://myunit.myproject:8000',
            SsylkaServisaUtil::sformirovat('ws', 8000, 'myunit', 'myproject', null)
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
    public function port_strokoi_privoditsya_k_celomu(): void
    {
        $this->assertSame(
            'ws://myunit.myproject:8000',
            SsylkaServisaUtil::sformirovat('ws', '8000', 'myunit', 'myproject', null)
        );
    }

    #[Test]
    public function http_bez_validnogo_proxy_hosta_daet_oshibku(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('unit.invalid_proxy_host');

        SsylkaServisaUtil::sformirovat('http', 8080, 'myunit', 'myproject', 'not-a-url');
    }
}
