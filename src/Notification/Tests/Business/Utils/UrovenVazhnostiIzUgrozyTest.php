<?php

declare(strict_types=1);

namespace App\Notification\Tests\Business\Utils;

use App\Notification\Business\Model\UrovenVazhnosti;
use App\Notification\Business\Utils\UrovenVazhnostiIzUgrozy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UrovenVazhnostiIzUgrozyTest extends TestCase
{
    #[Test]
    public function critical_i_blocker_daut_kritichnoe_uvedomlenie(): void
    {
        self::assertSame(UrovenVazhnosti::CRITICAL, UrovenVazhnostiIzUgrozy::dlyaUrovnyaRannera('critical'));
        self::assertSame(UrovenVazhnosti::CRITICAL, UrovenVazhnostiIzUgrozy::dlyaUrovnyaRannera('blocker'));
    }

    #[Test]
    public function warning_i_ok_ne_sozdayut_uvedomlenie(): void
    {
        self::assertNull(UrovenVazhnostiIzUgrozy::dlyaUrovnyaRannera('warning'));
        self::assertNull(UrovenVazhnostiIzUgrozy::dlyaUrovnyaRannera('ok'));
        self::assertNull(UrovenVazhnostiIzUgrozy::dlyaUrovnyaRannera('unknown'));
    }

    #[Test]
    public function prochtenie_trebuetsya_tolko_dlya_attention_i_critical(): void
    {
        self::assertFalse(UrovenVazhnosti::INFO->trebueProchteniya());
        self::assertTrue(UrovenVazhnosti::ATTENTION->trebueProchteniya());
        self::assertTrue(UrovenVazhnosti::CRITICAL->trebueProchteniya());
    }
}
